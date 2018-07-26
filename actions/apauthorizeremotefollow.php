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
 * Authorize Remote Follow
 *
 * @category  Plugin
 * @package   GNUsocial
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://www.gnu.org/software/social/
 */
class apAuthorizeRemoteFollowAction extends Action
{
    /**
     * Prepare to handle the Authorize Remote Follow request.
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     * @param array $args
     * @return boolean
     */
    protected function prepare(array $args=array())
    {
        parent::prepare($args);

        if (!common_logged_in()) {
            // XXX: selfURL() didn't work. :<
            common_set_returnto($_SERVER['REQUEST_URI']);
            if (Event::handle('RedirectToLogin', array($this, null))) {
                common_redirect(common_local_url('login'), 303);
            }
            return false;
        } else {
            if (!isset($_GET["acct"])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Handle the Authorize Remote Follow Request.
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     */
    protected function handle()
    {
        $other = Activitypub_profile::get_from_uri($_GET["acct"]);
        $actor_profile = common_current_user()->getProfile();
        $object_profile = $other->local_profile();
        if (!Subscription::exists($actor_profile, $object_profile)) {
            Subscription::start($actor_profile, $object_profile);
        }
        try {
            $postman = new Activitypub_postman($actor_profile, [$other]);
            $postman->follow();
        } catch (Exception $e) {
            // Meh, let the exception go on its merry way, it shouldn't be all
            // that important really.
        }
        common_redirect(common_local_url('userbyid', array('id' => $other->profile_id)), 303);
    }
}
