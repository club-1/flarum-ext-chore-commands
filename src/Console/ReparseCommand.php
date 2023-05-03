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

use Flarum\Console\AbstractCommand;
use Flarum\Formatter\Formatter;
use Flarum\Post\Post;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

class ReparseCommand extends AbstractCommand
{
    /** @var Formatter */
    protected $formatter;

    public function __construct(Formatter $formatter) {
        parent::__construct();
        $this->formatter = $formatter;
    }

    protected function configure()
    {
        $this
            ->setName('chore:reparse')
            ->setDescription('Reparse all comment posts using the latest formatter\'s configuration')
            ->addOption('chunk-size', 'c', InputOption::VALUE_REQUIRED, 'Number of rows by chunk of posts to retreive from the DB', 500)
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Reply "yes" to all questions');
    }

    protected function fire()
    {
        $in = $this->input;
        $io = new SymfonyStyle($in, $this->output);

        // Validate arguments & options
        $chunkSize = $in->getOption('chunk-size');
        if (!is_numeric($chunkSize)) {
            throw new InvalidArgumentException('chunk-size option must be a numeric value');
        }
        $chunkSize = intval($chunkSize);
        if ($chunkSize < 20) {
            $io->warning('A small chunk size will lower performances');
        } elseif ($chunkSize > 10000) {
            $io->warning('A chunk size too big could cause "out of memory" errors');
        }

        $io->warning('This will reparse all the comment posts in the database with the latest formatter\'s configuration');
        if (!$in->getOption('yes') && !$io->confirm('Do you want to continue?', false)) {
            return static::FAILURE;
        }
        // Flush formatter to be sure to have the latest configuration
        $this->formatter->flush();
        $posts = Post::query()
            ->where('type', 'comment')
            ->select(['id', 'content', 'user_id', 'edited_user_id'])
            ->lazyById($chunkSize);
        $posts = $io->progressIterate($posts);
        $changed = 0;
        foreach ($posts as $post) {
            assert($post instanceof Post);
            try {
                $src = $this->formatter->unparse($post->content, $post);
                $user = is_null($post->editedUser) ? $post->user : $post->editedUser;
                $content = $this->formatter->parse($src, $post, $user);
            } catch (\Throwable $exception) {
                $io->warning("Failed to reparse post $post->id, skipped it: {$exception->getMessage()}");
                continue;
            }
            if ($post->content != $content) {
                $post->content = $content;
                $post->save();
                $changed++;
            }
        }
        $io->success("$changed post(s) changed");
        return static::SUCCESS;
    }
}
