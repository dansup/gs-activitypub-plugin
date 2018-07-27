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
 * ActivityPub Profile
 *
 * @category  Plugin
 * @package   GNUsocial
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://www.gnu.org/software/social/
 */
class Activitypub_profile extends Profile
{
    public $__table = 'Activitypub_profile';

    protected $_profile = null;

    /**
     * Return table definition for Schema setup and DB_DataObject usage.
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     * @return array array of column definitions
     */
    public static function schemaDef()
    {
        return array(
                'fields' => array(
                    'uri' => array('type' => 'varchar', 'length' => 191, 'not null' => true),
                    'profile_id' => array('type' => 'integer'),
                    'inboxuri' => array('type' => 'varchar', 'length' => 191),
                    'sharedInboxuri' => array('type' => 'varchar', 'length' => 191),
                    'created' => array('type' => 'datetime', 'not null' => true),
                    'modified' => array('type' => 'datetime', 'not null' => true),
                ),
                'primary key' => array('uri'),
                'unique keys' => array(
                    'Activitypub_profile_profile_id_key' => array('profile_id'),
                    'Activitypub_profile_inboxuri_key' => array('inboxuri'),
                ),
                'foreign keys' => array(
                    'Activitypub_profile_profile_id_fkey' => array('profile', array('profile_id' => 'id')),
                ),
            );
    }

    /**
     * Generates a pretty profile from a Profile object
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     * @param Profile $profile
     * @return pretty array to be used in a response
     */
    public static function profile_to_array($profile)
    {
        $uri = ActivityPubPlugin::actor_uri($profile);
        $id = $profile->getID();
        $res = [
            '@context' => [
                "https://www.w3.org/ns/activitystreams",
                "https://w3id.org/security/v1",
                [
                    'manuallyApprovesFollowers' => 'as=>manuallyApprovesFollowers',
                    'sensitive'                 => 'as=>sensitive',
                    'movedTo'                   => 'as=>movedTo',
                    'Hashtag'                   => 'as=>Hashtag',
                    'ostatus'                   => 'http=>//ostatus.org#',
                    'atomUri'                   => 'ostatus=>atomUri',
                    'inReplyToAtomUri'          => 'ostatus=>inReplyToAtomUri',
                    'conversation'              => 'ostatus=>conversation',
                    'schema'                    => 'http=>//schema.org#',
                    'PropertyValue'             => 'schema=>PropertyValue',
                    'value'                     => 'schema=>value'
                ]
            ],
            'id'                => $uri,
            'type'              => 'Person',
            'following'         => common_local_url("apActorFollowing", array("id" => $id)),
            'followers'         => common_local_url("apActorFollowers", array("id" => $id)),
            'liked'             => common_local_url("apActorLiked", array("id" => $id)),
            'inbox'             => common_local_url("apActorInbox", array("id" => $id)),
            'preferredUsername' => $profile->getNickname(),
            'name'              => $profile->getFullname(),
            'summary'           => ($desc = $profile->getDescription()) == null ? "" : $desc,
            'url'               => $profile->getUrl(),
            'manuallyApprovesFollowers' => false,
            'tag' => [],
            'attachment' => [],
            'icon' => [
                'type'      => 'Image',
                'mediaType' => 'image/jpeg',
                'url'       => $profile->avatarUrl()
            ]
        ];

        if ($profile->isLocal()) {
            $res['endpoints']['sharedInbox'] = common_local_url("apSharedInbox", array("id" => $id));
        } else {
            $aprofile = new Activitypub_profile();
            $aprofile = $aprofile->from_profile($profile);
            $res['endpoints']['sharedInbox'] = $aprofile->sharedInboxuri;
        }

        return $res;
    }

    /**
     * Insert the current objects variables into the database
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     * @access public
     * @throws ServerException
     */
    public function do_insert()
    {
        $profile = new Profile();

        $profile->created = $this->created = $this->modified = common_sql_now();

        $fields = [
                    'uri'      => 'profileurl',
                    'nickname' => 'nickname',
                    'fullname' => 'fullname',
                    'bio'      => 'bio'
                    ];

        foreach ($fields as $af => $pf) {
            $profile->$pf = $this->$af;
        }

        $this->profile_id = $profile->insert();
        if ($this->profile_id === false) {
            $profile->query('ROLLBACK');
            throw new ServerException('Profile insertion failed.');
        }

        $ok = $this->insert();

        if ($ok === false) {
            $profile->query('ROLLBACK');
            throw new ServerException('Cannot save ActivityPub profile.');
        }
    }

    /**
     * Fetch the locally stored profile for this Activitypub_profile
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     * @return Profile
     * @throws NoProfileException if it was not found
     */
    public function local_profile()
    {
        $profile = Profile::getKV('id', $this->profile_id);
        if (!$profile instanceof Profile) {
            throw new NoProfileException($this->profile_id);
        }
        return $profile;
    }

    /**
     * Generates an Activitypub_profile from a Profile
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     * @param Profile $profile
     * @return Activitypub_profile
     * @throws Exception if no Activitypub_profile exists for given Profile
     */
    public static function from_profile(Profile $profile)
    {
        $profile_id = $profile->getID();

        $aprofile = self::getKV('profile_id', $profile_id);
        if (!$aprofile instanceof Activitypub_profile) {
            // No Activitypub_profile for this profile_id,
            if (!$profile->isLocal()) {
                // create one!
                $aprofile = self::create_from_local_profile($profile);
            } else {
                throw new Exception('No Activitypub_profile for Profile ID: '.$profile_id. ', this is a local user.');
            }
        }

        foreach ($profile as $key => $value) {
            $aprofile->$key = $value;
        }

        return $aprofile;
    }

    /**
     * Given an existent local profile creates an ActivityPub profile.
     * One must be careful not to give a user profile to this function
     * as only remote users have ActivityPub_profiles on local instance
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     * @param Profile $profile
     * @return Activitypub_profile
     */
    private static function create_from_local_profile(Profile $profile)
    {
        $url = $profile->getURL();
        $inboxes = Activitypub_explorer::get_actor_inboxes_uri($url);

        $aprofile->created = $aprofile->modified = common_sql_now();

        $aprofile                 = new Activitypub_profile;
        $aprofile->profile_id     = $profile->getID();
        $aprofile->uri            = $url;
        $aprofile->nickname       = $profile->getNickname();
        $aprofile->fullname       = $profile->getFullname();
        $aprofile->bio            = substr($profile->getDescription(), 0, 1000);
        $aprofile->inboxuri       = $inboxes["inbox"];
        $aprofile->sharedInboxuri = $inboxes["sharedInbox"];

        $aprofile->insert();

        return $aprofile;
    }

    /**
     * Returns sharedInbox if possible, inbox otherwise
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     * @return string Inbox URL
     */
    public function get_inbox()
    {
        if (is_null($this->sharedInboxuri)) {
            return $this->inboxuri;
        }

        return $this->sharedInboxuri;
    }

    /**
     * Getter for uri property
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     * @return string URI
     */
    public function get_uri()
    {
        return $this->uri;
    }

    /**
     * Ensures a valid Activitypub_profile when provided with a valid URI.
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     * @param string $url
     * @return Activitypub_profile
     * @throws Exception if it isn't possible to return an Activitypub_profile
     */
    public static function get_from_uri($url)
    {
        $explorer = new Activitypub_explorer();
        $profiles_found = $explorer->lookup($url);
        if (!empty($profiles_found)) {
            return self::from_profile($profiles_found[0]);
        } else {
            throw new Exception('No valid ActivityPub profile found for given URI.');
        }
    }

    /**
     * Look up, and if necessary create, an Activitypub_profile for the remote
     * entity with the given webfinger address.
     * This should never return null -- you will either get an object or
     * an exception will be thrown.
     *
     * @author GNU Social
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     * @param string $addr webfinger address
     * @return Activitypub_profile
     * @throws Exception on error conditions
     */
    public static function ensure_web_finger($addr)
    {
        // Normalize $addr, i.e. add 'acct:' if missing
        $addr = Discovery::normalize($addr);

        // Try the cache
        $uri = self::cacheGet(sprintf('activitypub_profile:webfinger:%s', $addr));

        if ($uri !== false) {
            if (is_null($uri)) {
                // Negative cache entry
                // TRANS: Exception.
                throw new Exception(_m('Not a valid webfinger address (via cache).'));
            }
            try {
                return self::get_from_uri($uri);
            } catch (Exception $e) {
                common_log(LOG_ERR, sprintf(__METHOD__ . ': Webfinger address cache inconsistent with database, did not find Activitypub_profile uri==%s', $uri));
                self::cacheSet(sprintf('activitypub_profile:webfinger:%s', $addr), false);
            }
        }

        // Now, try some discovery

        $disco = new Discovery();

        try {
            $xrd = $disco->lookup($addr);
        } catch (Exception $e) {
            // Save negative cache entry so we don't waste time looking it up again.
            // @todo FIXME: Distinguish temporary failures?
            self::cacheSet(sprintf('activitypub_profile:webfinger:%s', $addr), null);
            // TRANS: Exception.
            throw new Exception(_m('Not a valid webfinger address.'));
        }

        $hints = array_merge(
                    array('webfinger' => $addr),
                DiscoveryHints::fromXRD($xrd)
                );

        // If there's an Hcard, let's grab its info
        if (array_key_exists('hcard', $hints)) {
            if (!array_key_exists('profileurl', $hints) ||
                        $hints['hcard'] != $hints['profileurl']) {
                $hcardHints = DiscoveryHints::fromHcardUrl($hints['hcard']);
                $hints = array_merge($hcardHints, $hints);
            }
        }

        // If we got a profile page, try that!
        $profileUrl = null;
        if (array_key_exists('profileurl', $hints)) {
            $profileUrl = $hints['profileurl'];
            try {
                common_log(LOG_INFO, "Discovery on acct:$addr with profile URL $profileUrl");
                $aprofile = self::get_from_uri($hints['profileurl']);
                self::cacheSet(sprintf('activitypub_profile:webfinger:%s', $addr), $aprofile->get_uri());
                return $aprofile;
            } catch (Exception $e) {
                common_log(LOG_WARNING, "Failed creating profile from profile URL '$profileUrl': " . $e->getMessage());
                // keep looking
                                //
                                // @todo FIXME: This means an error discovering from profile page
                                // may give us a corrupt entry using the webfinger URI, which
                                // will obscure the correct page-keyed profile later on.
            }
        }

        // XXX: try hcard
        // XXX: try FOAF

        // TRANS: Exception. %s is a webfinger address.
        throw new Exception(sprintf(_m('Could not find a valid profile for "%s".'), $addr));
    }
}
