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
if (!defined('GNUSOCIAL')) {
    exit(1);
}

/**
 * Actor's profile (Local users only)
 *
 * @category  Plugin
 * @package   GNUsocial
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://www.gnu.org/software/social/
 */
class apActorProfileAction extends ManagedAction
{
    protected $needLogin = false;
    protected $canPost   = true;

    /**
     * Handle the Actor Profile request
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     * @return void
     */
    protected function handle()
    {
        if (!empty($id = $this->trimmed('id'))) {
            try {
                $profile = Profile::getByID($id);
            } catch (Exception $e) {
                ActivityPubReturn::error('Invalid Actor URI.', 404);
            }
            unset($id);
        } else {
            try {
                $profile = User::getByNickname($this->trimmed('nickname'))->getProfile();
            } catch (Exception $e) {
                ActivityPubReturn::error('Invalid username.', 404);
            }
        }

        if (!$profile->isLocal()) {
            ActivityPubReturn::error("This is not a local user.");
        }

        $res = Activitypub_profile::profile_to_array($profile);

        ActivityPubReturn::answer($res);
    }
}
