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
 * Remote Follow
 *
 * @category  Plugin
 * @package   GNUsocial
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://www.gnu.org/software/social/
 */
class apRemoteFollowAction extends Action
{
    public $nickname;
    public $local_profile;
    public $remote_identifier;
    public $err;

    /**
     * Prepare to handle the Remote Follow request.
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     * @param array $args
     * @return boolean
     */
    protected function prepare(array $args=array())
    {
        parent::prepare($args);

        if (common_logged_in()) {
            // TRANS: Client error.
            $this->clientError(_m('You can use the local subscription!'));
        }

        // Local user the remote wants to subscribe to
        $this->nickname = $this->trimmed('nickname');
        $this->local_profile = User::getByNickname($this->nickname)->getProfile();

        // Webfinger or profile URL of the remote user
        $this->remote_identifier = $this->trimmed('remote_identifier');

        return true;
    }

    /**
     * Handle the Remote Follow Request.
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     */
    protected function handle()
    {
        parent::handle();

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            /* Use a session token for CSRF protection. */
            $token = $this->trimmed('token');
            if (!$token || $token != common_session_token()) {
                // TRANS: Client error displayed when the session token does not match or is not given.
                $this->showForm(_m('There was a problem with your session token. '.
                                  'Try again, please.'));
                return;
            }
            $this->activitypub_connect();
        } else {
            $this->showForm();
        }
    }

    /**
     * Form.
     *
     * @author GNU Social
     * @param string|null $err
     */
    public function showForm($err = null)
    {
        $this->err = $err;
        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            // TRANS: Form title.
            $this->element('title', null, _m('TITLE', 'Subscribe to user'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $this->showContent();
            $this->elementEnd('body');
            $this->endHTML();
        } else {
            $this->showPage();
        }
    }

    /**
     * Page content.
     *
     * @author GNU Social
     */
    public function showContent()
    {
        // TRANS: Form legend. %s is a nickname.
        $header = sprintf(_m('Subscribe to %s'), $this->nickname);
        // TRANS: Button text to subscribe to a profile.
        $submit = _m('BUTTON', 'Subscribe');
        $this->elementStart(
            'form',
            ['id' => 'form_activitypub_connect',
                                          'method' => 'post',
                                          'class' => 'form_settings',
                                          'action' => common_local_url(
                                              'apRemoteFollow',
                                            ['nickname' => $this->nickname]
                                          )
                                    ]
                            );
        $this->elementStart('fieldset');
        $this->element('legend', null, $header);
        $this->hidden('token', common_session_token());

        $this->elementStart('ul', 'form_data');
        $this->elementStart('li', array('id' => 'activitypub_nickname'));

        // TRANS: Field label.
        $this->input(
            'nickname',
            _m('User nickname'),
            $this->nickname,
                     // TRANS: Field title.
                     _m('Nickname of the user you want to follow.')
        );

        $this->elementEnd('li');
        $this->elementStart('li', array('id' => 'activitypub_profile'));
        // TRANS: Field label.
        $this->input(
            'remote_identifier',
            _m('Profile Account'),
            $this->remote_identifier,
                      // TRANS: Tooltip for field label "Profile Account".
                     _m('Your account ID (e.g. user@example.net).')
        );
        $this->elementEnd('li');
        $this->elementEnd('ul');
        $this->submit('submit', $submit);
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    /**
     * Start connecting the two instances (will be finished with the authorization)
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     * @return void
     */
    public function activitypub_connect()
    {
        $remote_profile = null;
        try { // Try with ActivityPub system
            $remote_profile = Activitypub_profile::get_from_uri($this->remote_identifier);
        } catch (Exception $e) { // Fallback to compatibility WebFinger system
            $validate = new Validate();
            $opts = array('allowed_schemes' => array('http', 'https', 'acct'));
            if ($validate->uri($this->remote_identifier, $opts)) {
                $bits = parse_url($this->remote_identifier);
                if ($bits['scheme'] == 'acct') {
                    $remote_profile = $this->connect_webfinger($bits['path']);
                }
            } elseif (strpos($this->remote_identifier, '@') !== false) {
                $remote_profile = $this->connect_webfinger($this->remote_identifier);
            }
        }
        if (!empty($remote_profile)) {
            $url = ActivityPubPlugin::stripUrlPath($remote_profile->get_uri())."activitypub/authorize_follow?acct=".$this->local_profile->getUri();
            common_log(LOG_INFO, "Sending remote subscriber $this->remote_identifier to $url");
            common_redirect($url, 303);
            return;
        }

        // TRANS: Client error.
        $this->clientError(_m('Must provide a remote profile.'));
    }

    /**
     * This function is used by activitypub_connect () and
     * is a step of the process
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     * @param type $acct
     * @return Profile Profile resulting of WebFinger connection
     */
    private function connect_webfinger($acct)
    {
        $link = ActivityPubPlugin::pull_remote_profile($acct);
        if (!is_null($link)) {
            return $link;
        }
        // TRANS: Client error.
        $this->clientError(_m('Could not confirm remote profile address.'));
    }

    /**
     * Page title
     *
     * @return string Page title
     */
    public function title()
    {
        // TRANS: Page title.
        return _m('ActivityPub Connect');
    }
}
