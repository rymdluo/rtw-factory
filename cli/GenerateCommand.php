<?php
namespace Grav\Plugin\Console;

use Grav\Console\ConsoleCommand;
use Symfony\Component\Console\Input\InputArgument;

class GenerateCommand extends ConsoleCommand
{
  protected $options = [];

  protected function configure()
  {
    $this
    ->setName("g")
    ->setName("gen")
    ->setName("generate")
    ->setDescription("Generates static site")
    ->addArgument(
      'path',
      InputArgument::OPTIONAL,
      'Regenerate everything'
    )
    ;
  }

  protected function serve()
  {

      require(__DIR__.'/../classes/RollingCurl.php');
      require(__DIR__.'/../classes/Request.php');

      define(GRAV_HTTP, 'https://preview.teashade.com', GRAV_ROOT);
      
      echo "grabbing from " . GRAV_HTTP . "\n"; 
      
    function super_pull($urls) {
        
        echo "super_pulling now \n"; 
        
        $rollingCurl = new RollingCurl();
         
        foreach ($urls as $url) {
            $rollingCurl->get(GRAV_HTTP . $url);
        }
        
        $start = microtime(true);
        echo "Fetching..." . PHP_EOL;
        
        $rollingCurl
            ->setOptions(array(
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_6_8) AppleWebKit/534.30 (KHTML, like Gecko) Chrome/12.0.742.112 Safari/534.30', 
                CURLOPT_FRESH_CONNECT => true, 
                CURLOPT_RETURNTRANSFER => 1, 
                CURLOPT_FOLLOWLOCATION => 1,
                CURLOPT_CONNECTTIMEOUT => 60,
                CURLOPT_TIMEOUT        => 60,
            ))
            ->setCallback(function(\Grav\Plugin\Console\Request $request, \Grav\Plugin\Console\RollingCurl  $rollingCurl) {

                $response =  $request->getResponseText();
                $response = str_replace('<meta id="rtw" name="robots" content="noindex, nofollow">', '', $response);
                $response = str_replace('rtw.', '', $response);
                
                $event_horizon = '/srv/users/serverpilot/apps/teashade-preview/rtw-hstlr';
                $page_dir = explode(".com", $request->getUrl());
                
                if ($page_dir[1]){
                    $page_dir = $event_horizon . $page_dir[1];
                } else {
                    $page_dir = $event_horizon;   
                }
                echo "Working on " . $page_dir . "... \n";
                
                if (!is_dir($page_dir)) { mkdir($page_dir, 0755, true); }
                file_put_contents($page_dir . '/index.html', $response);
                
                // Clear list of completed requests and prune pending request queue to avoid memory growth
                $rollingCurl->clearCompleted();
                //$rollingCurl->prunePendingRequestQueue();
                
                echo "Generation complete for (" . $request->getUrl() . ")" . PHP_EOL;
                
            })
            ->setSimultaneousLimit(25)
            ->execute();
        
            echo "...done in " . (microtime(true) - $start) . PHP_EOL;

    }
      
    function pull($url) {
      $pull = curl_init();
      curl_setopt($pull, CURLOPT_USERAGENT,'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_6_8) AppleWebKit/534.30 (KHTML, like Gecko) Chrome/12.0.742.112 Safari/534.30');
      curl_setopt($pull, CURLOPT_FRESH_CONNECT, TRUE);
      curl_setopt($pull, CURLOPT_URL, GRAV_HTTP . $url);
      curl_setopt($pull, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($pull, CURLOPT_FOLLOWLOCATION, 1);
      $emit = curl_exec($pull);
      curl_close($pull);
      $emit = str_replace('<meta id="rtw" name="robots" content="noindex, nofollow">', '', $emit);
      $emit = str_replace('rtw.', '', $emit);
      return $emit;
    }
      
    // set build dir
    
    $event_horizon = '/srv/users/serverpilot/apps/teashade-preview/rtw-hstlr';
    
    echo "deploying to " . $event_horizon . "\n";
      
    // make build dir
    if (!is_dir(dirname($event_horizon))) { mkdir(dirname($event_horizon), 0755, true); }

    // get page routes
    if ($this->input->getArgument('path') && $this->input->getArgument('path') === 'all'){
        $pages = json_decode(pull('/footer/rtwpages' . $this->input->getArgument('path')));
        $taxonomies = json_decode(pull('/footer/rtwtaxonomies'));
    } else if ($this->input->getArgument('path') && $this->input->getArgument('path') === 'pages'){
        $pages = json_decode(pull('/footer/rtw' . $this->input->getArgument('path')));
    }  else if ($this->input->getArgument('path') && $this->input->getArgument('path') === 'taxonomies'){
        $pages = json_decode(pull('/footer/rtw' . $this->input->getArgument('path')));
    } else {
        $pages = json_decode(pull('/footer/rtwpages'));
        $taxonomies = json_decode(pull('/footer/rtwtaxonomies'));
    }
    // make pages in build dir
    if ($pages) {
        super_pull($pages);
    }
      
    // make taxonomies in build dir
    if ($taxonomies) {
        super_pull($taxonomies);
    }
  }
}