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
    exit(1);
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

  $today_midnight = strtotime('today midnight');
  $current_year = date('Y');
  $discussions = [];
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

      //20191117 - for sorting
      //web ui doesn't list year so ...
      //if discussion day of current year is > today assume discussion was in previous year, otherwise use current year
      //fuzzy cuz of time zones and possibly very old (> 1 year) discussions
      $discussion_possible_time = strtotime("$day $current_year");
      $discussion_year = $discussion_possible_time > $today_midnight ? $current_year - 1 : $current_year;
      $key = $discussion_year . date('md', strtotime("$day $discussion_year"));

      $discussions[$key][] = "<p><b>{$member} on {$day}:</b> $text<br><a href='https://www.meetup.com{$link}' rel='nofollow'>{$link}</a></p>";
    }
  }

  krsort($discussions);

  $messages = [];
  foreach($discussions as $date => $discussion_list)
  {
    $messages[] = join("\n", $discussion_list);
  }

  if(file_put_contents(
    __DIR__ . '/docs/index.html',
    str_replace(
      '{{ @messages }}',
      join("\n", $messages),
      file_get_contents(__DIR__ . '/templates/layout.html')
    )
  ))
  {
    echo 'Wrote ' . count($messages) . " messages.\n";
  }
}
catch(Exception $e)
{
  echo $e->getMessage();
  exit(1);
}


