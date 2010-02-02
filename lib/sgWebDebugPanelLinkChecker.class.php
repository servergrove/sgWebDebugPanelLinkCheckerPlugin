<?php

class sgWebDebugPanelLinkChecker extends sfWebDebugPanel
{
  public function getTitle()
  {
    $parameters = $this->webDebug->getOption('request_parameters');
    if (!isset($parameters['check_links']))
    {
      return;
    }

    return 'Links';
  }

  public function getPanelTitle()
  {
    return 'Links';
  }

  public function getPanelContent()
  {

    if ( ! function_exists( 'curl_init') )
    {
        return 'Curl extension not enabled';
    }

    $results = array();

    $content = '<style>.linkok {color: green} .linkfail {color: red}</style>';

    $response = sfContext::getInstance()->getResponse();

    $rspContent = $response->getContent();

    $pattern = '/(href|src)=[\'"]?([^\'" >]+)[\'" >]/';

    $allOk = true;

    // Grabs all links from HTML
    if ( preg_match_all($pattern, $rspContent, $matches) )
    {
      $content .= '<ul>';
      foreach( $matches[2] as $link )
      {

        $res = 'N/A';
        $class = '';

        $uri = $link;


        if ( empty( $link ) || $link[0] == '#' || strpos( $link, 'javascript') === 0 )
        {
          // empty link or just a local anchor. do nothing
        }
        elseif ( stripos( $link, 'http:') !== 0 &&  stripos( $link, 'ftp:') !== 0 )
        {
          // this is not an absolute url.

          if ( $link[0] == '/' )
          {
            // absolute path, add host.
            $uri = $_SERVER['HTTP_HOST'].$link;

          }
          else
          {
            $lastSlashPos = strrpos( $_SERVER['REQUEST_URI'], '/' );

            $uri = $_SERVER['HTTP_HOST'].substr( $_SERVER['REQUEST_URI'], 0, $lastSlashPos ).'/'.$link;
          }
          $uri = 'http://'.$uri;

          try
          {
            $head = $this->httpHeadCurl( $uri );

            $res = strpos( $head, '404 Not Found' ) === false ? 'OK' : 'FAIL';
            $class = ' class='.( $res == 'OK' ? 'linkok' : 'linkfail' );
          }
          catch( Exception $ex )
          {
            $this->setStatus(sfLogger::ERR);

            return 'Error: '.$ex->getMessage();
          }

        }

        if ( $res == 'FAIL' )
        {
          $allOk = false;
        }

        $results[$res][] = "<li$class>".$res." - <a href=\"".htmlentities( $uri )."\">".htmlentities( $link )."</a></li>\n";
      }

      $content .= implode( "\n", $results['FAIL'] );
      $content .= implode( "\n", $results['N/A'] );
      $content .= implode( "\n", $results['OK'] );

      $content .= '</ul>';
    }

    if ( ! $allOk )
    {
      $this->setStatus(sfLogger::ERR);

    }

    return $content;
  }


  public static function listenToLoadDebugWebPanelEvent(sfEvent $event)
  {
    $event->getSubject()->setPanel(
        'linkchecker',
        new self($event->getSubject())
    );
  }


  function httpHeadCurl($url)
  {
    if (!extension_loaded('curl_init') || !function_exists('curl_init'))
    {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_TIMEOUT, 30);
      curl_setopt($ch, CURLOPT_HEADER, 1);
      curl_setopt($ch, CURLOPT_NOBODY, 1);
      curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, 1);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      $result = curl_exec($ch);
      curl_close($ch);
      return $result;
    }
    throw new Exception("Curl extension not enabled" );
  }
}