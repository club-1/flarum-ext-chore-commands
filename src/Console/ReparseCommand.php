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
use Flarum\Post\CommentPost;
use Flarum\Post\Post;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

class ReparseCommand extends AbstractCommand
{
    const CHUNK_SIKE = 100;

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
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Reply "yes" to all questions');
    }

    protected function fire()
    {
        $in = $this->input;
        $io = new SymfonyStyle($in, $this->output);
        $io->warning('This will reparse all the comment posts in the database with the latest formatter\'s configuration');
        if (!$in->getOption('yes') && !$io->confirm('Do you want to continue?', false)) {
            return static::FAILURE;
        }
        // Flush formatter to be sure to have the latest configuration
        $this->formatter->flush();
        $posts = Post::query()
            ->where('type', 'comment')
            ->select(['id', 'content', 'user_id', 'edited_user_id'])
            ->lazyById(static::CHUNK_SIKE);
        $posts = $io->progressIterate($posts);
        foreach ($posts as $post) {
            assert($post instanceof CommentPost);
            $src = $this->formatter->unparse($post->content, $post);
            $user = is_null($post->editedUser) ? $post->user : $post->editedUser;
            $post->content = $this->formatter->parse($src, $post, $user);
            $post->save();
        }
    }
}
