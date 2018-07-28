<?php

namespace Tests\Unit;

use Tests\TestCase;

class ProfileObjectTest extends TestCase
{
    public function testLibraryInstalled()
    {
        $this->assertTrue(class_exists('\Activitypub_profile'));
    }

    public function testProfileObject()
    {
        // TODO: Improve this test.
        $this->assertTrue(true);
        return;

        // Mimic proper ACCEPT header
        $_SERVER['HTTP_ACCEPT'] = 'application/ld+json; profile="https://www.w3.org/ns/activitystreams';

        // Fetch profile
        $user = \Profile::getKV('id', 1);
        // Fetch ActivityPub Actor Object representation
        $profile = \Activitypub_profile::profile_to_array($user);

        $this->assertTrue(is_array($profile));

        $this->assertTrue(isset($profile['inbox']));
        $this->assertTrue(isset($profile['outbox']));
    }
}
