<?php
/**
 * GNU social - a federating social network
 *
 * Plugin that handles ActivityPub
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Plugin
 * @package   GNUsocial
 * @author    Daniel Supernault <danielsupernault@gmail.com>
 * @copyright 2014 Free Software Foundation http://fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      https://www.gnu.org/software/social/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class ActivityPubPlugin extends Plugin
{

    public function onRouterInitialized(URLMapper $m)
    {
        $m->connect('api/statuses/user_timeline/:id.ap',
                    ['action' => 'activitypubactor'],
                    ['id'     => '[0-9]+']);
        
        $m->connect('api/user/:id/liked.json',
                    ['action' => 'likedactor'],
                    ['id'     => '[0-9]+']);
    }

    public function onPluginVersion(array &$versions)
    {
        $versions[] = [ 'name' => 'ActivityPub',
                        'version' => GNUSOCIAL_VERSION,
                        'author' => 'Daniel Supernault',
                        'homepage' => 'https://www.gnu.org/software/social/',
                        'rawdescription' =>
                        // Todo: Translation
                        'Adds ActivityPub Support'];
        return true;
    }
}
