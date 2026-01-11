<?php

/*
 * This file is part of club-1/flarum-ext-chore-commands.
 *
 * Copyright (c) 2023 Nicolas Peugnet <nicolas@club1.fr>.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Club1\ChoreCommands\Tests\integration;

use Carbon\Carbon;
use Flarum\Extend;
use Flarum\Formatter\Formatter;
use Flarum\Testing\integration\ConsoleTestCase;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\User\User;
use Generator;
use InvalidArgumentException;
use s9e\TextFormatter\Configurator;
use s9e\TextFormatter\Parser\Tag;

class ReparseCommandTest extends ConsoleTestCase
{
    use RetrievesAuthorizedUsers;

    public function setUp(): void
    {
        parent::setUp();
        $this->extension('club-1-chore-commands');
        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
            ],
            'discussions' => [
                ['id' => 1, 'title' => 'some title', 'created_at' => Carbon::now(), 'last_posted_at' => Carbon::now(), 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1],
            ],
        ]);
    }

    /**
     * @dataProvider basicProvider
     * @param string[] $contents
     */
    public function testBasic(array $contents, int $expected, bool $transaction = true): void
    {
        $count = count($contents);
        $this->prepareDatabase(['posts' => array_map(function(int $id, string $content) {
            return ['id' => $id, 'number' => $id, 'discussion_id' => 1, 'created_at' => Carbon::now(), 'user_id' => 1, 'type' => 'comment', 'content' => $content];
        }, range(1, $count), $contents)]);
        $input = [
            'command' => 'chore:reparse',
            '--yes' => true,
            '--no-transaction' => !$transaction,
        ];
        $output = $this->runCommand($input);
        $this->assertStringContainsString("$count/$count", $output);
        $this->assertStringContainsString("[OK] $expected post(s) changed", $output);
    }

    public function basicProvider(): array
    {
        return [
            "no posts changed" => [
                ['<t>something</t>', '<t>something else</t>'],
                0,
            ],
            "one post changed" => [
                ['<t><p>something</p></t>', '<t>something else</t>'],
                1,
            ],
            "two post changed" => [
                ['<t><p>something</p></t>', '<t><TAG>something else</TAG></t>'],
                2,
            ],
            "5k posts changed" => [
                iterator_to_array($this->tagPostGenerator(5000)),
                5000,
            ],
            "no posts changed no trans" => [
                ['<t>something</t>', '<t>something else</t>'],
                0,
                false,
            ],
            "one post changed no trans" => [
                ['<t><p>something</p></t>', '<t>something else</t>'],
                1,
                false,
            ],
            "two post changed no trans" => [
                ['<t><p>something</p></t>', '<t><TAG>something else</TAG></t>'],
                2,
                false,
            ],
            "5k posts changed no trans" => [
                iterator_to_array($this->tagPostGenerator(5000)),
                5000,
                false,
            ],
        ];
    }

    protected function tagPostGenerator(int $count): Generator
    {
        for ($i = 0; $i < $count; $i++) {
            yield "<t><TAG>something $i</TAG><t>";
        }
    }

    /**
     * @dataProvider optionExceptionsProvider
     */
    public function testOptionExceptions(array $options, string $message): void
    {
        $input = [
            'command' => 'chore:reparse',
            '--yes' => true
        ];
        $input = array_merge($input, $options);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        $this->console()->setCatchExceptions(false);
        $this->runCommand($input);
    }

    public function optionExceptionsProvider(): array
    {
        return [
            [['--chunk-size' => 'test'], 'chunk-size option must be a numeric value'],
        ];
    }

    /**
     * @dataProvider optionWarningsProvider
     */
    public function testOptionWarnings(array $options, string $message): void
    {
        $input = [
            'command' => 'chore:reparse',
            '--yes' => true
        ];
        $input = array_merge($input, $options);
        $output = $this->runCommand($input);
        $this->assertStringContainsString("[WARNING] $message", $output);
    }

    public function optionWarningsProvider(): array
    {
        return [
            [['--chunk-size' => 10], 'A small chunk size will lower performances'],
            [['--chunk-size' => 12345], 'A chunk size too big could cause "out of memory" errors'],
        ];
    }

    public static function filterActor(Tag $tag, User $actor): void
    {
        $tag->setAttribute('actor', strval($actor->id));
    }

    public function testExtNeedsActor(): void
    {
        $this->prepareDatabase(['posts' => [
            ['id' => 1, 'number' => 1, 'discussion_id' => 1, 'created_at' => Carbon::now(), 'user_id' => null, 'type' => 'comment', 'content' => '<t>[actor]</t>'],
            ['id' => 2, 'number' => 2, 'discussion_id' => 1, 'created_at' => Carbon::now(), 'user_id' => 1, 'type' => 'comment', 'content' => '<t>[actor]</t>'],
        ]]);

        $this->extend((new Extend\Formatter)->configure(function (Configurator $conf) {
            $conf->BBCodes->addCustom('[actor]', '<span>{@actor}</span>');
            $filter = $conf->tags['actor']->filterChain->append([static::class, 'filterActor']);;
            $filter->addParameterByName('actor');
        }));
        $this->app()->getContainer()->make(Formatter::class)->flush();

        $input = [
            'command' => 'chore:reparse',
            '--yes' => true
        ];
        $output = $this->runCommand($input);
        $this->assertStringContainsString('changed: 1  skipped: 1', $output);
        $this->assertStringContainsString('[WARNING] 1 post(s) skipped, see the log in', $output);
        $this->assertStringContainsString("[OK] 1 post(s) changed", $output);
        preg_match('/see the log in\s+([\w\/-]+)/', $output, $matches);
        $temp = $matches[1];
        $log = file_get_contents($temp);
        $this->assertStringContainsString('Failed to reparse post 1, skipped it:', $log);
        $this->assertStringContainsString('Club1\ChoreCommands\Tests\integration\ReparseCommandTest::filterActor()', $log);
        unlink($temp);
    }

    public function testSaveFailure(): void
    {
        $this->prepareDatabase(['posts' => [
            ['id' => 1, 'number' => 1, 'discussion_id' => 1, 'created_at' => Carbon::now(), 'user_id' => 1, 'type' => 'comment', 'content' => '<t>[repeat char="A"]</t>'],
            ['id' => 2, 'number' => 2, 'discussion_id' => 1, 'created_at' => Carbon::now(), 'user_id' => 1, 'type' => 'comment', 'content' => '<t><TAG>should change</TAG></t>'],
        ]]);

        $this->extend((new Extend\Formatter)->configure(function (Configurator $conf) {
            $conf->BBCodes->addCustom('[repeat char={TEXT}]', '<span>{@char}</span>');
            $filter = $conf->tags['repeat']->attributes['char']->filterChain->append('str_repeat');
            $filter->addParameterByValue((1 << 24) - 76);
        }));
        $this->app()->getContainer()->make(Formatter::class)->flush();
        $this->console()->setCatchExceptions(false);

        $input = [
            'command' => 'chore:reparse',
            '--yes' => true
        ];
        $output = $this->runCommand($input);
        $this->assertStringContainsString('changed: 0  skipped: 1', $output);
        $this->assertStringContainsString('changed: 1  skipped: 1', $output);
        $this->assertStringContainsString('[WARNING] 1 post(s) skipped, see the log in', $output);
        preg_match('/see the log in\s+([\w\/-]+)/', $output, $matches);
        $temp = $matches[1];
        $log = file_get_contents($temp);
        $this->assertStringContainsString('Failed to reparse post 1, skipped it:', $log);
        unlink($temp);
    }
}
