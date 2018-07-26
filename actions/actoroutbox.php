<?php

if (!defined('GNUSOCIAL')) { exit(1); }

class ActorOutboxAction extends ManagedAction
{
    protected $needLogin = false;
    protected $canPost   = true;

    protected function handle()
    {
      $nickname = $this->trimmed('nickname');
      $res = $this->generateOutbox($nickname);
      header('Content-Type: application/json');
      echo $this->output($res);
    }

    protected function output($res)
    {
      return json_encode($res, JSON_PRETTY_PRINT);
    }

    protected function generateOutbox($nickname)
    {
      try {
        $user = User::getByNickname($nickname);
        $profile = $user->getProfile();
        $url = $profile->profileurl;
      } catch (Exception $e) {
        throw new \Exception('Invalid username');
      }

      $notices = [];
      $notice = $profile->getNotices();
      while ($notice->fetch()) {
        $note = $notice;

        // TODO: Handle other types
        if($note->object_type == 'http://activitystrea.ms/schema/1.0/note') {
          $notices[] = $this->noticeToObject($note);
        }
      }

      $res = [
        '@context' => [
          "https://www.w3.org/ns/activitystreams",
          [
            "@language" => "en"
          ]
        ],
        'id' => "{$url}/outbox.json",
        'type'  => 'OrderedCollection',
        'totalItems' => $profile->noticeCount(),
        'orderedItems' => $notices
      ];
      return $res;
    }

    protected function noticeToObject($notice)
    {
      // todo: fix timestamp formats
      $item = [
        'id'  => $notice->getUrl(),
        // TODO: handle other types
        'type' => 'Create',
        'actor' => $notice->getProfile()->getUrl(),
        'published' => $notice->created,
        'to' => [
          'https://www.w3.org/ns/activitystreams#Public'
        ],
        'cc' => [
          "{$notice->getProfile()->getUrl()}/subscribers",
        ],
        'object' => [
          'id' => $notice->getUrl(),

          // TODO: handle other types
          'type' => 'Note',

          // XXX: CW Title
          'summary' => null,
          'content' => $notice->getRendered(),
          'inReplyTo' => empty($notice->reply_to) ? null : Notice::getById($notice->reply_to)->getUrl(),

          // TODO: fix date format
          'published' => $notice->created,
          'url' => $notice->getUrl(),
          'attributedTo' => $notice->getProfile()->getUrl(),
          'to' => [
            // TODO: handle proper scope
            'https://www.w3.org/ns/activitystreams#Public'
          ],
          'cc' => [
            // TODO: add cc's
            "{$notice->getProfile()->getUrl()}/subscribers",
          ],
          'sensitive' => null,
          'atomUri' => $notice->getUrl(),
          'inReplyToAtomUri' => null,
          'conversation' => $notice->getUri(),
          'attachment' => [],
          'tag' => []
        ]
      ];
      return $item;
    }

}