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

// Validate data
if (!isset ($data->type)) {
        ActivityPubReturn::error ("Type was not specified.");
}

switch ($data->object->type) {
case "Like":
        try {
                // Validate data
                if (!isset ($data->object->object)) {
                        ActivityPubReturn::error ("Object Notice URL was not specified.");
                }
                Fave::removeEntry ($actor_profile, Notice::getByUri ($data->object->object));
                ActivityPubReturn::answer ("Notice disfavorited successfully.");
        } catch (Exception $e) {
                ActivityPubReturn::error ($e->getMessage (), 403);
        }
        break;
case "Follow":
        // Validate data
        if (!isset ($data->object->object)) {
                ActivityPubReturn::error ("Object Actor URL was not specified.");
        }
        // Get valid Object profile
        try {
                $object_profile = new Activitypub_explorer;
                $object_profile = $object_profile->lookup ($data->object->object)[0];
        } catch (Exception $e) {
                ActivityPubReturn::error ("Invalid Object Actor URL.", 404);
        }

        try {
                if (Subscription::exists ($actor_profile, $object_profile)) {
                        Subscription::cancel ($actor_profile, $object_profile);
                        ActivityPubReturn::answer ("You are no longer following this person.");
                } else {
                        ActivityPubReturn::error ("You are not following this person already.", 409);
                }
        } catch (Exception $e) {
                ActivityPubReturn::error ("Invalid Object Actor URL.", 404);
        }
        break;
default:
        ActivityPubReturn::error ("Invalid object type.");
        break;
}
