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
 * ActivityPub Keys System
 *
 * @category  Plugin
 * @package   GNUsocial
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://www.gnu.org/software/social/
 */
class Activitypub_rsa extends Managed_DataObject
{
    public $__table = 'Activitypub_rsa';

    /**
     * Return table definition for Schema setup and DB_DataObject usage.
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     * @return array array of column definitions
     */
    public static function schemaDef()
    {
        return [
                'fields' => [
                    'profile_id'  => ['type' => 'integer'],
                    'private_key' => ['type' => 'varchar',  'length'   => 191],
                    'public_key'  => ['type' => 'varchar',  'length'   => 191],
                    'created'     => ['type' => 'datetime', 'not null' => true],
                    'modified'    => ['type' => 'datetime', 'not null' => true],
                ],
                'primary key' => ['profile_id'],
                'unique keys' => [
                    'Activitypub_rsa_profile_id_key'  => ['profile_id'],
                    'Activitypub_rsa_private_key_key' => ['private_key'],
                    'Activitypub_rsa_public_key_key'  => ['public_key'],
                ],
                'foreign keys' => [
                    'Activitypub_profile_profile_id_fkey' => ['profile', ['profile_id' => 'id']],
                ],
        ];
    }

    /**
     * Guarantees a Public Key for a given profile.
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     * @param Profile $profile
     * @return string The public key
     * @throws Exception It should never occur
     */
    public function ensure_public_key($profile)
    {
        $this->profile_id = $profile->getID();
        $apRSA = self::getKV('profile_id', $this->profile_id);
        if (!$apRSA instanceof Activitypub_rsa) {
            // No existing key pair for this profile
            if ($profile->isLocal()) {
                self::generate_keys($this->private_key, $this->public_key);
                $this->store_keys();
            } else {
                throw new Exception('No Keys for this Profile. That\'s odd.');
            }
        }
        return $apRSA->public_key;
    }

    /**
     * Insert the current object variables into the database.
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     * @access public
     * @throws ServerException
     */
    public function store_keys()
    {
        $this->created = $this->modified = common_sql_now();
        $ok = $this->insert();
        if ($ok === false) {
            $profile->query('ROLLBACK');
            throw new ServerException('Cannot save ActivityPub RSA.');
        }
    }

    /**
     * Generates a pair of RSA keys.
     *
     * @author PHP Manual Contributed Notes <dirt@awoms.com>
     * @param string $private_key in/out
     * @param string $public_key in/out
     */
    public static function generate_keys(&$private_key, &$public_key)
    {
        $config = [
            'digest_alg'       => 'sha512',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        // Create the private and public key
        $res = openssl_pkey_new($config);

        // Extract the private key from $res to $private_key
        openssl_pkey_export($res, $private_key);

        // Extract the public key from $res to $pubKey
        $pubKey = openssl_pkey_get_details($res);
        $public_key = $pubKey["key"];
        unset($pubKey);
    }
}
