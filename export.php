<?php

//$html_array = json_decode(file_get_contents('html_array'), true);
$img_array = json_decode(file_get_contents('img_array'), true);

login();

sendPages('out');

function httpRequest($post, $login = false)
{
    $user_agent = 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.9) Gecko/20071025 Firefox/2.0.0.9';
    $url = 'http://localhost/mw/api.php';

    $ch = curl_init();
	curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_COOKIEFILE, "cookie.tmp");
    if($login)
    {
        echo "is login\n";
        curl_setopt($ch, CURLOPT_COOKIEJAR, "cookie.tmp");
    }
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
	//curl_setopt($ch, CURLOPT_VERBOSE, true);

    $result = curl_exec($ch);
        // var_dump($result);
        //echo PHP_EOL;

    curl_close($ch);

    return $result;
}

function login()
{
    $post = 'action=login&lgname=andrew&lgpassword=andr00&format=xml';

    $result = httpRequest($post, true);
    $dom = domDocument::loadXML($result);
    $login = $dom->getElementsByTagName('login')->item(0);
    $token = $login->getAttribute('token');
	
    if(!$token)
    {
	die('failed to get login token!');
    }
    else
    {
	echo "just got token, is $token" . PHP_EOL;
    }

    $post .= "&lgtoken=$token";
    httpRequest($post, true);
}

function editPage($page)
{
	//$post = "action=query&format=xml&prop=info|revisions&intoken=edit&titles=$title";
    $post = "action=tokens&format=xml";

	$result = httpRequest($post);

	$dom = domDocument::loadXML($result);
	$edit = $dom->getElementsByTagName('tokens')->item(0);
	$etoken = $edit->getAttribute('edittoken');

	if(!$etoken)
	{
	    die('failed to get etoken!');
	}
	else
	{
//	    echo "just got etoken, is $etoken";
	}

    $title = $page['title'];
    $content = $page['content'];

    //$summary = $title . '-- HTML Import';
	//$post = "action=edit&format=xml&title=$title&summary=$summary&text=$content&token=$etoken";
    $post = array('action' => 'edit', 'format' => 'xml', 'title' => $title, 'text' => $content, 'token' => $etoken);

	$result = httpRequest($post);

    if(stripos($result, 'Success') !== false)
    {
        echo "Success\n";
    }
    else
    {
        echo "Failure, output is: \n";
        var_dump($result);
    }
}

function sendPages($dir)
{
    $working_dir = array_slice(scandir($dir), 2);
//        var_dump($working_dir);

    $total = count($working_dir);
    $i = 0;

    foreach($working_dir as $file)
    {
        //var_dump($pages);

        $title = $file;
        $content = file_get_contents("out/$title");

            echo "Attempting to upload $title, page $i of $total... ";

        $page = ['title' => $title, 'content' => $content];
//        echo "dumping page\n";
//        var_dump($page);

        editPage($page);

        $i++;
    }
}

function sendImages($img_array)
{
     $total = count($img_array);
     $i = 1;

     foreach($img_array as $img)
     {
         $file = $img['name'];
         $name = $img['wiki_name'];

         //$post = "action=query&format=xml&prop=info&intoken=edit&titles=$name";
         $post = "action=tokens&format=xml";
    //     echo "dumping initial post request: \n";
    //     var_dump($post);

         $result = httpRequest($post);
         $dom = domDocument::loadXML($result);
         $edit = $dom->getElementsByTagName('tokens')->item(0);
         $etoken = $edit->getAttribute('edittoken');

         if(!$etoken)
         {
             die('failed to get etoken!');
         }
         else
         {
      //       echo "just got etoken, is $etoken" . PHP_EOL;
         }

         $post = array('action' => 'upload', 'format' => 'xml', 'filename' => "$name", 'file' => "@$file", 'token' => "$etoken");

             //echo "dumping post request:\n";
             // var_dump($post);
             echo "Attempting to upload $name, image $i of $total... ";
         $result = httpRequest($post);

         if(stripos($result, 'Success') !== false)
         {
             echo "Success\n";
         }
         else
         {
             echo "Failure, output is: \n";
             var_dump($result);
         }

     $i++;
    }
}

?>
