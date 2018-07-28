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

$valid_object_types = array("Note");

// Validate data
if (!isset($data->id)) {
    ActivityPubReturn::error("Id not specified.");
}
if (!(isset($data->object->type) && in_array($data->object->type, $valid_object_types))) {
    ActivityPubReturn::error("Invalid Object type.");
}
if (!isset($data->object->content)) {
    ActivityPubReturn::error("Object content was not specified.");
}
if (!isset($data->object->url)) {
    ActivityPubReturn::error("Object url was not specified.");
} elseif (!filter_var($data->object->url, FILTER_VALIDATE_URL)) {
    ActivityPubReturn::error("Invalid Object URL.");
}
if (!isset($data->object->to)) {
    ActivityPubReturn::error("Object To was not specified.");
}

$content = $data->object->content;

$act = new Activity();
$act->verb = ActivityVerb::POST;
$act->time = time();
$act->actor = $actor_profile->asActivityObject();

$act->context = new ActivityContext();

// Is this a reply?
if (isset($data->object->reply_to)) {
    try {
        $reply_to = Notice::getByUri($data->object->reply_to);
    } catch (Exception $e) {
        ActivityPubReturn::error("Invalid Object reply_to value.");
    }
    $act->context->replyToID  = $reply_to->getUri();
    $act->context->replyToUrl = $reply_to->getUrl();
} else {
    $reply_to = null;
}

$act->context->attention = common_get_attentions($content, $actor_profile, $reply_to);

$discovery = new Activitypub_explorer;
if ($to_profiles == "https://www.w3.org/ns/activitystreams#Public") {
    $to_profiles = array();
}
// Generate To objects
if (is_array($data->object->to)) {
    // Remove duplicates from To actors set
    array_unique($data->object->to);
    foreach ($data->object->to as $to_url) {
        try {
            $to_profiles = array_merge($to_profiles, $discovery->lookup($to_url));
        } catch (Exception $e) {
            // XXX: Invalid actor found, not sure how we handle those
        }
    }
} elseif (empty($data->object->to) || in_array($data->object->to, $public_to)) {
    // No need to do anything else at this point, let's just break out the if
} else {
    try {
        $to_profiles[]= $discovery->lookup($data->object->to);
    } catch (Exception $e) {
        ActivityPubReturn::error("Invalid Actor.", 404);
    }
}
unset($discovery);

foreach ($to_profiles as $to) {
    $act->context->attention[ActivityPubPlugin::actor_uri($to)] = "http://activitystrea.ms/schema/1.0/person";
}

// Reject notice if it is too long (without the HTML)
// This is done after MediaFile::fromUpload etc. just to act the same as the ApiStatusesUpdateAction
if (Notice::contentTooLong($content)) {
    ActivityPubReturn::error("That's too long. Maximum notice size is %d character.");
}

$options = array('source' => 'ActivityPub', 'uri' => isset($data->id) ? $data->id : $data->object->url, 'url' => $data->object->url);
// $options gets filled with possible scoping settings
ToSelector::fillActivity($this, $act, $options);

$actobj = new ActivityObject();
$actobj->type = ActivityObject::NOTE;
$actobj->content = common_render_content($content, $actor_profile, $reply_to);

// Finally add the activity object to our activity
$act->objects[] = $actobj;

try {
    $res = Activitypub_create::create_to_array(
            $data->id,
            $data->actor,
                    Activitypub_notice::notice_to_array(Notice::saveActivity($act, $actor_profile, $options))
        );
    ActivityPubReturn::answer($res);
} catch (Exception $e) {
    ActivityPubReturn::error($e->getMessage());
}
