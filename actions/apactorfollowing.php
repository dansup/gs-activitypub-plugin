<?php
/**
 * GNU social - a federating social network
 *
 * ActivityPubPlugin implementation for GNU Social
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
 * @copyright 2018 Free Software Foundation http://fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      https://www.gnu.org/software/social/
 */
if (!defined ('GNUSOCIAL')) {
        exit (1);
}

/**
 * Actor's Following Collection
 *
 * @category  Plugin
 * @package   GNUsocial
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://www.gnu.org/software/social/
 */
class apActorFollowingAction extends ManagedAction
{
        protected $needLogin = false;
        protected $canPost   = true;

        /**
         * Handle the Following Collection request
         *
         * @author Diogo Cordeiro <diogo@fc.up.pt>
         * @return void
         */
        protected function handle ()
        {
                $nickname = $this->trimmed ('nickname');
                try {
                        $user    = User::getByNickname ($nickname);
                        $profile = $user->getProfile ();
                        $url     = $profile->profileurl;
                } catch (Exception $e) {
                        ActivityPubReturn::error ('Invalid username.');
                }

                if (!isset ($_GET["page"])) {
                        $page = 1;
                } else {
                        $page = intval ($this->trimmed ('page'));
                }

                if ($page <= 0) {
                        ActivityPubReturn::error ('Invalid page number.');
                }

                /* Fetch Following */
                try {
                        $since = ($page - 1) * PROFILES_PER_MINILIST;
                        $limit = (($page - 1) == 0 ? 1 : $page) * PROFILES_PER_MINILIST;
                        $sub   = $profile->getSubscribed ($since, $limit);
                } catch (NoResultException $e) {
                        ActivityPubReturn::error ('This user is not following anyone.');
                }

                /* Calculate total items */
                $total_subs  = $profile->subscriptionCount();
                $total_pages = ceil ($total_subs / PROFILES_PER_MINILIST);

                if ($total_pages == 0) {
                        ActivityPubReturn::error ('This user is not following anyone.');
                }

                if ($page > $total_pages) {
                        ActivityPubReturn::error ("There are only {$total_pages} pages.");
                }

                /* Get followed' URLs */
                $subs = array ();
                while ($sub->fetch ()) {
                        $subs[] = $sub->profileurl;
                }

                $res = [
                  '@context'     => [
                    "https://www.w3.org/ns/activitystreams",
                    "https://w3id.org/security/v1",
                  ],
                  'id'           => "{$url}/following.json",
                  'type'         => ($page == 0 ? 'OrderedCollection' : 'OrderedCollectionPage'),
                  'totalItems'   => $total_subs,
                  'next'         => $page+1 > $total_pages ? null : "{$url}/followers.json?page=".($page+1 == 1 ? 2 : $page+1),
                  'prev'         => $page == 1 ? null : "{$url}/followers.json?page=".($page-1 <= 0 ? 1 : $page-1),
                  'orderedItems' => $subs
                ];

                ActivityPubReturn::answer ($res);
        }
}
