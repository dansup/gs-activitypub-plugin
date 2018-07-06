<?php
/**
 * GNU social - a federating social network
 *
 * Todo: Description
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
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @author    Daniel Supernault <danielsupernault@gmail.com>
 * @copyright 2015 Free Software Foundaction, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      https://gnu.io/social
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class apActorFollowersAction extends ManagedAction
{
    protected $needLogin = false;
    protected $canPost   = true;

    protected function handle()
    {
        $nickname = $this->trimmed('nickname');
        try {
          $user    = User::getByNickname($nickname);
          $profile = $user->getProfile();
          $url     = $profile->profileurl;
        } catch (Exception $e) {
          ActivityPubReturn::error ('Invalid username');
        }

        $page = intval($this->trimmed('page'));

        if ($page <= 0)
            ActivityPubReturn::error ('Invalid page number');

        /* Fetch Followers */
        try {
            $since = ($page-1) * PROFILES_PER_MINILIST;
            $limit = (($page-1) == 0 ? 1 : $page)*PROFILES_PER_MINILIST;
            $sub = $profile->getSubscribers($since, $limit);
        } catch (NoResultException $e) {
            ActivityPubReturn::error ('This user has no followers');
        }

        /* Calculate total items */
        $total_subs  = $profile->subscriberCount();
        $total_pages = ceil($total_subs/PROFILES_PER_MINILIST);

        if ($total_pages == 0)
            ActivityPubReturn::error ('This user has no followers');

        if ($page > $total_pages)
            ActivityPubReturn::error ("There are only {$total_pages} pages");

        /* Get followers' URLs */
        $subs = [];
        while ($sub->fetch())
          $subs[] = $this->pretty_sub (clone($sub));

        $res = [
          '@context'          => [
            "https://www.w3.org/ns/activitystreams",
            "https://w3id.org/security/v1",
          ],
          'id'                => "{$url}/followers.json",
          'type'              => ($page == 0 ? 'OrderedCollection' : 'OrderedCollectionPage'),
          'totalItems'        => $total_subs,
          'next'              => $page+1 > $total_pages ? null : "{$url}/followers.json?page=".($page+1 == 1 ? 2 : $page+1),
          'prev'              => $page == 1 ? null : "{$url}/followers.json?page=".($page-1 <= 0 ? 1 : $page-1),
          'orderedItems'      => $subs
        ];

        ActivityPubReturn::answer ($res);
    }

    protected function pretty_sub ($sub_object)
    {
        return $sub_object->profileurl;
    }
}
