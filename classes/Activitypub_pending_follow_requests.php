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
 * ActivityPub's Pending follow requests
 *
 * @category  Plugin
 * @package   GNUsocial
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://www.gnu.org/software/social/
 */
class Activitypub_pending_follow_requests extends Managed_DataObject
{
    public $__table = 'Activitypub_pending_follow_requests';
    public $local_profile_id;
    public $remote_profile_id;
    private $_reldb = null;

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
                    'local_profile_id'  => array('type' => 'integer', 'not null' => true),
                    'remote_profile_id' => array('type' => 'integer', 'not null' => true),
                    'relation_id'       => array('type' => 'serial',  'not null' => true),
                ),
                'primary key' => array('relation_id'),
                'unique keys' => array(
                    'Activitypub_pending_follow_requests_relation_id_key' => array('relation_id'),
                ),
                'foreign keys' => array(
                    'Activitypub_pending_follow_requests_local_profile_id_fkey'  => array('profile', array('local_profile_id' => 'id')),
                    'Activitypub_pending_follow_requests_remote_profile_id_fkey' => array('profile', array('remote_profile_id' => 'id')),
                ),
            );
    }

    public function __construct($actor, $remote_actor)
    {
        $this->local_profile_id  = $actor;
        $this->remote_profile_id = $remote_actor;
    }

    /**
     * Add Follow request to table.
     *
     * @author Diogo Cordeiro
     * @param int32 $actor actor id
     * @param int32 $remote_actor remote actor id
     */
    public function add()
    {
        return !$this->exists() && $this->insert();
    }

    public function exists()
    {
        $this->_reldb = clone ($this);
        if ($this->_reldb->find() > 0) {
            $this->_reldb->fetch();
            return true;
        }
        return false;
    }

    public function remove()
    {
        return $this->exists() && $this->_reldb->delete();
    }
}
