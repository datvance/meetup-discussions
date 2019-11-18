<?php

include_once './vendor/autoload.php';

//https://www.meetup.com/Ski-CO/discussions/
$meetups = [
  'Ski-CO',
  'BoulderSkiClub',
  'IKON-Ski-Board',
  'Denver-Snowboarding-Meetup-EPIC-IKON-and-more',
  'ODCO4Fun'
];

$client = new GuzzleHttp\Client([
  'cookies' => true,
  'headers' => [
    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:68.0) Gecko/20100101 Firefox/68.0',
  ],
]);

try
{
  $res = $client->get('https://secure.meetup.com/login/');
  $cookies = $res->getHeader('set-cookie');
  $meetup_crsf = '';
  foreach($cookies as $cookie)
  {
    if(strpos($cookie, 'MEETUP_CSRF') !== FALSE)
    {
      $meetup_crsf = str_replace('MEETUP_CSRF=', '', strtok($cookie, ';'));
    }
  }

  if(!$meetup_crsf)
  {
    echo 'no meetup csrf';
    exit;
  }

  $login_form_parameters = [
    'email' => getenv('MEETUP_EMAIL'),
    'password' => getenv('MEETUP_PASSWORD'),
    'rememberme' => 'on',
    'token' => $meetup_crsf,
    'submitButton' => 'Log in',
    'returnUri' => 'https://www.meetup.com/',
    'op' => 'login',
    'apiAppClientId' => '',
  ];

  $res = $client->post('https://secure.meetup.com/login/', [
    'form_params' => $login_form_parameters
  ]);

  $current_year = date('Y');
  $messages = [];
  foreach($meetups as $meetup)
  {
    $discussion_page = 'https://www.meetup.com/' . $meetup . '/discussions/';
    sleep(1);
    $res = $client->get($discussion_page);
    $discussion_html = $res->getBody()->getContents();

    preg_match_all('@<div class="discussion\-card card display\-\-block border\-\-none border\-\-none">.*?<div class="discussion\-card card display\-\-block border\-\-none border\-\-none">@', $discussion_html, $d);
    if(!isset($d[0]) || !$d[0])
    {
      echo "Could not parse {$meetup} discussions\n";
      continue;
    }

    $member = '';
    $day = '';
    $text = '';
    $link = '';
    foreach($d[0] as $discussion)
    {
      preg_match('@<img.*?alt="(.*?)".*?>@', $discussion, $m);
      if(isset($m[1]) && $m[1])
      {
        $member = $m[1];
      }

      preg_match('@<span>(.*?)</span>@', $discussion, $y);
      if(isset($y[1]) && $y[1])
      {
        $day = $y[1];
      }

      preg_match('@<p class="discussion\-card\-\-desc text\-\-wrapNice">(.*?)</p>@', $discussion, $t);
      if(isset($t[1]) && $t[1])
      {
        $text = $t[1];
      }

      preg_match('@<a class="discussion\-card\-\-link" href="(.*?)">@', $discussion, $l);
      if(isset($l[1]) && $l[1])
      {
        $link = $l[1];
      }

      //20191117
      $key = $current_year . date('md', strtotime("$day $current_year"));
      $messages[$key] = "<p><b>{$member} on {$day}:</b> $text<br><a href='https://www.meetup.com{$link}' rel='nofollow'>{$link}</a></p>";
    }
  }

  krsort($messages);

  file_put_contents(
    __DIR__ . '/docs/index.html',
    str_replace(
      '{{ @messages }}',
      join("\n", $messages),
      file_get_contents(__DIR__ . '/templates/layout.html')
    )
  );
}
catch(Exception $e)
{
  echo $e->getMessage();
}


