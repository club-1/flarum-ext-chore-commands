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

namespace Club1\ChoreCommands\Console;

use Carbon\Carbon;
use Flarum\Extend;
use Flarum\Formatter\Formatter;
use Flarum\Testing\integration\ConsoleTestCase;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\User\User;
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
     */
    public function testBasic($contents, $expected): void
    {
        $this->prepareDatabase(['posts' => array_map(function(int $id, string $content) {
            return ['id' => $id, 'number' => $id, 'discussion_id' => 1, 'created_at' => Carbon::now(), 'user_id' => 1, 'type' => 'comment', 'content' => $content];
        }, range(1, count($contents)), $contents)]);
        $input = [
            'command' => 'chore:reparse',
            '--yes' => true
        ];
        $output = $this->runCommand($input);
        $this->assertStringContainsString('2/2', $output);
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
        ];
    }

    public static function filterActor(Tag $tag, User $actor) {
        $tag->setAttribute('actor', $actor->id);
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
        $this->assertStringContainsString('[WARNING] Failed to reparse post 1, skipped it', $output);
        $this->assertStringContainsString('Club1\ChoreCommands\Console\ReparseCommandTest::filterActor()', $output);
        $this->assertStringContainsString('Argument #2 ($actor) must be of type Flarum\User\User, null given', $output);
        $this->assertStringContainsString("[OK] 1 post(s) changed", $output);
    }
}
