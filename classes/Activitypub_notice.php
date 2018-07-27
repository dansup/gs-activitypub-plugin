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
 * @author    Daniel Supernault <danielsupernault@gmail.com>
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @copyright 2018 Free Software Foundation http://fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      https://www.gnu.org/software/social/
 */
if (!defined('GNUSOCIAL')) {
    exit(1);
}

/**
 * ActivityPub notice representation
 *
 * @category  Plugin
 * @package   GNUsocial
 * @author    Daniel Supernault <danielsupernault@gmail.com>
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://www.gnu.org/software/social/
 */
class Activitypub_notice extends Managed_DataObject
{
    /**
     * Generates a pretty notice from a Notice object
     *
     * @author Daniel Supernault <danielsupernault@gmail.com>
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     * @param Notice $notice
     * @return pretty array to be used in a response
     */
    public static function notice_to_array($notice)
    {
        $attachments = array();
        foreach ($notice->attachments() as $attachment) {
            $attachments[] = Activitypub_attachment::attachment_to_array($attachment);
        }

        $tags = array();
        foreach ($notice->getTags() as $tag) {
            if ($tag != "") {       // Hacky workaround to avoid stupid outputs
                $tags[] = Activitypub_tag::tag_to_array($tag);
            }
        }

        $to = array();
        foreach ($notice->getAttentionProfiles() as $to_profile) {
            $to[] = $to_profile->getUri();
        }
        if (empty($to)) {
            $to = array("https://www.w3.org/ns/activitystreams#Public");
        }

        $item = [
                        'id'           => $notice->getUri(),
                        'type'         => 'Note',
                        'actor'        => $notice->getProfile()->getUrl(),
                        'published'    => $notice->getCreated(),
                        'to'           => $to,
                        'content'      => $notice->getContent(),
                        'url'          => $notice->getUrl(),
                        'reply_to'     => empty($notice->reply_to) ? null : Notice::getById($notice->reply_to)->getUri(),
                        'is_local'     => $notice->isLocal(),
                        'conversation' => $notice->getConversationUrl(),
                        'attachment'   => $attachments,
                        'tag'          => $tags
                ];

        return $item;
    }
}
