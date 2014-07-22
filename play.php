<?php
class htmlParser
{
    protected $external_stack, $closing_nodes, $temp_data, $amazonFinalOutput;

    public function __construct($page_array)
    {
        $this->closing_nodes = ['div','p','i','emphasis','b','strong','a','img', 'li'];

        foreach($page_array as $page)
        {
            $this->temp_data['page'] = $page['name'];
            $this->temp_data['out_name'] = $page['wiki_name'];

//                echo "dumping temp_data before page removal\n";
//                var_dump($this->temp_data);

            $this->processPage($page['name']);
            unset($this->temp_data['page'], $this->temp_data['text'], $this->temp_data['out_name']);

                echo "dumping after\n";
                var_dump($this->temp_data);
        }
    }

    public function processPage($page)
    {
        $page_master_node = $this->loadPage($page);

        $page_output = $this->processNode($page_master_node);
//            var_dump($page_output);

        if(!empty($this->amazonFinalOutput))
        {
            $page_output .= "\n==Product Links==\n";
            $page_output .= $this->amazonFinalOutput;
        }

        $page_output = preg_replace('/^\s*/sm', '', $page_output); // remove spaces at the beginning of lines
        $page_output = preg_replace('/\n/', "\n\n", $page_output); // remove excess newlines
//        $page_output = preg_replace('/\n/', "\n\n", $page_output); // remove excess newlines
        $page_output = str_replace("''''''", "", $page_output); // remove double empty bold
        $page_output = str_replace("''''''''", "", $page_output); // remove double empty bold
        $page_output = preg_replace("/'{4}(?!')/", '', $page_output);

        $output_name = $this->temp_data['out_name'];
//            echo "echoing output_name\n";
//            var_dump($output_name);

        file_put_contents("out/$output_name", $page_output);

        unset($this->amazonFinalOutput, $page_output);
    }

    public function loadPage($page)
    {
        $filtered_page = $this->filterPage($page);
//        $filtered_page = utf8_encode($filtered_page);

        $working_page = new domDocument;
        @$working_page->loadHTML($filtered_page);
        $working_page->preserveWhiteSpace = false;
        $working_page = $working_page->documentElement;

        return $working_page;
    }

    public function processNode($node, $internal_stack = null)
    {
//        echo "\nstart of processnode\n";
//        var_dump($internal_stack);
//        var_dump($this->external_stack);
        $output = '';

        if(!empty($this->external_stack))
        {
            $output .= $this->checkStack($internal_stack);
            $this->external_stack[] = "$node->nodeName";
        }
        else
        {
            $this->external_stack[] = "$node->nodeName";
        }

        $internal_stack[] = "$node->nodeName";

            echo "node is $node->nodeName \n";

        if($node instanceof DOMNode)
        {
            switch ($node->nodeType)
            {
                case XML_ELEMENT_NODE:
                    switch($node->nodeName)
                    {
                        case 'div':
                            echo "  **Div**\n\n";
                            break;

                        case 'p':
                            echo "  **Paragraph**\n";
                            break;

                        case 'i':
                        case 'emphasis':
                            echo "  **italic**\n";
                            $output .= $this->processItalic($node);
                            break;

                        case 'b':
                        case 'strong':
                            echo "  **bold**\n";
                            $output .= $this->processBold($node);
                            break;

                        case 'a':
                            echo "  **anchor**\n";
                                $output .= $this->processAnchor($node);
                            break;

                        case 'img':
                            echo "  **image**\n";
                            $output .= $this->processImage($node);
                            break;

                        case 'br':
                            echo "  **break**\n";
                            $output .= PHP_EOL;
                            break;
                    }
                    break;

                case XML_TEXT_NODE:
                    if(preg_match('/\w+/', $node->nodeValue)){

                        $processed_text = $this->cleanText($node);
                            var_dump($processed_text);

                        if(in_array('a', $internal_stack) || in_array('img', $internal_stack))
                        {
                            if(empty($this->temp_data['text']))
                            {
                                $this->temp_data['text'] = '';
                            }

                            $this->temp_data['text'] .= $processed_text;

                                echo "text node in anchor or img\n";
                                var_dump($this->temp_data['text']);
                        }
                        else
                        {
                            $output .= $processed_text;
                        }
                    }
                    break;

                /*case XML_CDATA_SECTION_NODE:
                    echo "is cdata, content is: \n";
                    echo $node->nodeValue;
                    break;*/

                case XML_COMMENT_NODE:
//                    echo "is comment\n";
                    break;
            }
        }

        if($node->hasChildNodes())
        {
            foreach($node->childNodes as $child)
            {
                $output .= $this->processNode($child, $internal_stack);
            }
        }

//        echo "end of processNode, output is:\n\n";
//        echo $output;

        return $output;
    }

    protected function closeElements($name)
    {
        $output = '';

        switch($name)
        {
            case 'div':
                $output = $this->processDiv("closing");
                break;

            case 'p':
                $output = $this->processParagraph("closing");
                break;

            case 'i':
            case 'emphasis':
                $output = $this->processItalic("closing");
                break;

            case 'b':
            case 'strong':
                $output = $this->processBold("closing");
                break;

            case 'a':
                $output = $this->processAnchor("closing");
                break;

            case 'img':
                $output = $this->processImage("closing");
                break;

            case 'li':
                echo "is list\n";
                $output = $this->processList("closing");
                break;
        }

        return $output;
    }

    protected function checkStack($internal)
    {
            $output = '';
            $difference = count($this->external_stack) - count($internal);

            if($difference > 0)
            {
                echo "about to pop off $difference elements\n";

                $i = 0;
                while($i < $difference)
                {
                    echo "popping off element $i \n";
                    $top_of_stack = array_pop($this->external_stack);

                    if(in_array($top_of_stack, $this->closing_nodes))
                    {
                        echo "closing element: " . $top_of_stack . PHP_EOL;

                        $output .= $this->closeElements($top_of_stack, $internal);
                    }
                    $i++;
                }
            }
        return $output;
    }

    protected function cleanText($node)
    {
        $output = '';

        $text = $node->nodeValue;
            echo "in cleanText, text is: $text\n";

        if(strpos($text, 'msimage') !== false)
        {
            return $output;
        }

        $output = preg_replace('/[\n\r]/', '', $text);

            echo "text after newlines removed: $output \n";

        $output = preg_replace('/\s+/', ' ', $output);

            echo "text after multiple spaces removed: $output \n";

        return $output;
    }

    protected function processList($node)
    {
        if($node == "closing")
        {
                echo "closing list\n";

            $output = PHP_EOL . PHP_EOL;

            return $output;
        }
        else
        {
            echo "error in processlist\n";
            return false;
        }
    }

    protected function processDiv($node)
    {
        if($node == "closing")
        {
            echo "closing div\n";
            $output = PHP_EOL;
            return $output;
        }
        else
        {
            echo "error in processDiv\n";
            return false;
        }
    }

    protected function processParagraph($node)
    {
        if($node == "closing")
        {
            echo "closing paragraph\n";

           /* if(in_array('li', $this->external_stack))
            {
                $output = '';
            }
            else
            {*/
                $output = PHP_EOL;
//            }
            return $output;
        }
        else
        {
            echo "error in processParagraph\n";
            return false;
        }
    }

    protected function processItalic($node)
    {
        if($node == "closing")
        {
            echo "closing italic\n";
        }
        else
        {
            echo "opening italic";
        }
        $output = "''";

        return $output;
    }

    protected function processBold($node)
    {
        if($node == "closing")
        {
            echo "closing bold\n";
        }
        else
        {
            echo "opening bold\n";
        }
        $output = "'''";


        return $output;
    }

    protected function processAnchor($node)
    {
        echo "in processAnchor\n";
        $output = '';

        if(!empty($this->temp_data['skip']))
        {
            unset($this->temp_data['skip']);
            return $output;
        }

        if($node == "closing")
        {
            $output .= $this->closeProcessAnchor();

            return $output;
        }
        elseif($node instanceof DOMElement) // is actual link element
        {
            if($node->hasAttribute('href'))
            {
                $href = $node->getAttribute('href');

                if($this->skipJunk($href))
                {
                    $this->temp_data['skip'] = true;
                    return '';
                }

                if(stripos($href, 'http:') !== false || stripos($href, 'ftp:') !== false) // is external link
                {
                        echo "is external link, href is $href\n";

                    if($node->hasAttribute('add_date') && !(in_array('li', $this->external_stack)))
                    {
                            echo "is amazon link! href is $href\n";

                        $this->temp_data['amazon'] = true;
                        $this->amazonFinalOutput .= $this->handleAmazon($node, $href);
                    }
                    else
                    {
                        $this->temp_data['href'] = $href;
                    }
                }
                else
                {
                    $resolved_link = $this->resolveLink($href);
                    if(!empty($resolved_link))
                    {
                        $wiki_name = $this->wikiName($resolved_link);
                    }
                    else
                    {
                        echo "resolved link was empty\n";
                        return '';
                    }

                    if(stripos($href, 'pdf') !== false)
                    {
                        $this->temp_data['img'] = true;
                    }

                    if(!empty($wiki_name))
                    {
                            echo "dumping wiki_name\n";
                            var_dump($wiki_name);
                        $this->temp_data['href'] = $wiki_name;
                    }
                    else
                    {
                        return '';
                    }
                        echo "dumping temp_data\n";
                        var_dump($this->temp_data);
                }
            }
        }

        if($node->hasAttribute('name'))
        {
                echo "is name link\n";
            $output .= $this->handleName($node);
        }

        return $output;
    }

    protected function handleName($node)
    {
        $output = '';

        if($node instanceof DOMElement)
        {
            $name = $node->getAttribute('name');

            $output = "\n\n\n==$name== " . PHP_EOL;
            echo "output is $output\n";

            $this->temp_data['skip'] = true;
        }

        return $output;
    }

    protected function handleAmazon($node, $href = null)
    {
        $output = '';

//        if(empty($href))
//        {
//            echo "href is empty, node is $node\n";
//        }

        if($node instanceof DOMElement) // opening link
        {
            echo "amazon, href is $href\n";

            $this->temp_data['href'] = $href;
        }

        elseif($node == "closing")
        {
            if(!empty($this->temp_data['img']))
            {
                    echo "temp_data img is\n";
                    var_dump($this->temp_data['img']);

                $output .= '[[File:' . $this->temp_data['img'];

                $output .= '|' . 'link=' . $this->temp_data['href'];

                if(!empty($this->temp_data['text']))
                {
                    $output .= '|' . $this->temp_data['text'];
                }

                $output .= ']]';

                if(!empty($this->temp_data['text']))
                {
                    $output .= ' ' . $this->temp_data['text'];
                }

                echo "closed amazon node with image, output is $output \n";
            }
            else
            {
                $output .= '[' . $this->temp_data['href'];

                if(!empty($this->temp_data['text']))
                {
                    $output .= ' ' . $this->temp_data['text'];
                }

                $output .= ']';
            }

            $output .= PHP_EOL;

            echo "closed amazon node, output is $output \n";
        }

        return $output;
    }

    protected function closeProcessAnchor()
    {
            echo "closing anchor\n";
        $output = '';

        if(!empty($this->temp_data['skip']))
        {
            unset($this->temp_data['skip']);
            return $output;
        }

        if(!empty($this->temp_data['img'])) // img is set
        {
            echo "is an image link\n";

            if(!empty($this->temp_data['amazon']))
            {
                    echo "about to close amazon link";

                $this->amazonFinalOutput .= $this->handleAmazon("closing");
            }
            else
            {
                    echo "about to output anchor with image\n";
                    var_dump($this->temp_data);

                if(is_string($this->temp_data['href']))
                {
                    echo "href is string, returning nothing (fixes File:h.t) \n";
                }
                else
                {
                    $output .= PHP_EOL . '[[File:' . $this->temp_data['href'][0] . "." . $this->temp_data['href'][1];

                    $output .= '|frameless|border';

                    if(!empty($this->temp_data['text']))
                    {
                        $output .= '|' . $this->temp_data['text'];
                    }

                    $output .= ']]';
                        echo "output of anchor with image: $output \n";
                }
            }
        }
        else
        {
            if(!empty($this->temp_data['href']))
            {
                if(is_string($this->temp_data['href']))
                {
                    // it's an external link

                    if(!empty($this->temp_data['amazon']))
                    {
                        echo "about to close amazon link";

                        $this->amazonFinalOutput .= $this->handleAmazon("closing");
                    }
                    else
                    {
                        $href = $this->temp_data['href'];
                        var_dump($href);

                        if(in_array('li', $this->external_stack)) //make sure every link in list begins with newline
                        {
                            $output .= PHP_EOL;
                        }

                        $output .= "http://www.robothumb.com/src/$href@160x120.jpg" . ' ' . '[' . $href;

                        if(!empty($this->temp_data['text']))
                        {
                            $output .= ' ' . $this->temp_data['text'];
                        }

                        $output .= ']' . PHP_EOL;
                    }
                }
                else
                {
                    $output .= PHP_EOL . '[[' . $this->temp_data['href'][0];

                    if(!empty($this->temp_data['text']))
                    {
                        $output .= '|' . $this->temp_data['text'];
                    }

                    $output .= ']]';
                }
            }
        }

            echo "output of anchor is $output\n";
            echo "unsetting parts of temp_data, dumping before:\n";
            var_dump($this->temp_data);

        unset($this->temp_data['href'], $this->temp_data['img'], $this->temp_data['text'], $this->temp_data['amazon']);

            echo "dumping after:\n";
            var_dump($this->temp_data);

        return $output;
    }

    protected function processImage($node)
    {
        $output = '';

        if(!empty($this->temp_data['skip']))
        {
                echo "in img, skipping \n";
            return $output;
        }

        if(in_array('a', $this->external_stack))
        {
                echo "this image is within an anchor, dumping stack and outputting src\n";
                var_dump($this->external_stack);

            if($node == "closing")
            {
                echo "closing img (in anchor) -- outputting nothing\n";
            }
            else
            {
                if($node instanceof DOMElement)
                {
                    $src = $node->getAttribute('src');
                    echo $src . PHP_EOL;

                    if(!empty($this->temp_data['href']))
                    {
                        echo "htm img link\n";
                        if(!empty($this->temp_data['amazon']))
                        {
                            $resolved_link = $this->resolveLink($src);
                            $wiki_name = $this->wikiName($resolved_link);
                            $wiki_name = $wiki_name[0] . '.' . $wiki_name[1];

                                echo "wiki_name in amazon is $wiki_name \n";

                            $this->temp_data['img'] = $wiki_name;
                        }
                        else // is html with image inside, temporary fix just skip image and leave the href alone
                        {
                            if(stripos($this->temp_data['href'][1], 'htm') !== false)
                            {
                                echo "htm with image inside it, skipping\n";
                                return '';
                            }
                        }

                        if(empty($this->temp_data['img']))
                        {
                            $this->temp_data['img'] = true;
                        }
                    }
                }
            }
        }
        else
        {
            if($node == "closing")
            {
                    echo "closing img\n";
//                    var_dump($internal);

                if(!empty($this->temp_data['img']))
                {
                    $wiki_name = $this->temp_data['img'];
                    $output .= PHP_EOL . '[[' . 'File:' . $wiki_name . '|frameless|border';
                }
                else
                {
                    return $output;
                }

                if(!empty($this->temp_data['text']))
                {
                    $output .= '|' . $this->temp_data['text'];
                }

                $output .= ']]';
                    echo "img ending output is $output\n";

                unset($this->temp_data['text'], $this->temp_data['img']);
            }
            else
            {
                if($node instanceof DOMElement)
                {
                    if($node->hasAttribute('src'))
                    {
                        $src = $node->getAttribute('src');

                        if(stripos($src, 'ballsnboxes') !== false)
                        {
                            echo "is scidot, skipping\n";
                            return $output;
                        }
                        elseif(stripos($src, 'bluedot') !== false)
                        {
                            echo "bluedot, skipping\n";
                            return $output;
                        }
                        elseif(stripos($src, 'dotclear') !== false)
                        {
                            echo "dotclear, skipping\n";
                            return $output;
                        }
                        elseif(stripos($src, 'ablbull') !== false)
                        {
                            echo "ablbull, skipping\n";
                            return $output;
                        }

                        $resolved_link = $this->resolveLink($src);
                        $wiki_name = $this->wikiName($resolved_link);
                        $wiki_name = $wiki_name[0] . '.' . $wiki_name[1];

                        $this->temp_data['img'] = $wiki_name;

                        echo "img starting output is: $output \n";
                    }
                }
                else
                {
                    echo "\nERROR, in img, element NOT DOMElement\n";
                }
            }
        }
        echo "img output is $output \n";

        return $output;
    }

    protected function skipJunk($href)
    {
        echo "in skipJunk\n";
        {
            if(strpos($href, 'mailto') !== false)
            {
                echo "is mailto, breaking\n";
                return true;
            }

            if(strpos($href, 'qksrv') !== false)
            {
                echo "is qksrv, breaking\n";
                return true;
            }

            if(strpos($href, 'adspeed') !== false)
            {
                echo "is adspeed, breaking\n";
                return true;
            }

            if(((strpos($href, 'file:')) !== false))
            {
                echo "is file:///, breaking\n";
                return true;
            }

            if(((strpos($href, 'google')) !== false))
            {
                echo "is google, breaking\n";
                return true;
            }
        }
        return false;
    }

    public function resolveLink($input_link)
    {
        $parent_page = $this->temp_data['page'];

        //remove whitespaces from parent_page and input_link
        $parent_page = preg_replace('/\s*/', '', $parent_page);

        if(preg_match('/^#/', $input_link))
        {
            echo "same page link, returning nothing\n";
            return '';
        }

            echo "input link before spaces removed $input_link \n";
        $input_link = preg_replace('/#\s*/', '#', $input_link);
//            echo "input link after: $input_link \n";

        $relative_dirs_count = substr_count($input_link, '../');
//            echo "relative dirs: " . $relative_dirs_count . PHP_EOL;

        //break the parent_file path into parts
        $parent_file_parts = explode('/', $parent_page);
//            echo "dumping after explode\n";
//            var_dump($parent_file_parts);

        if(strpos($input_link, '#') !== false) //we have an anchor
        {
            $parent_end = strtolower(array_pop($parent_file_parts));

            $pos = strpos($input_link, '#');
            $end_without_hash = strtolower(substr($input_link, 0, $pos));

            echo "parent_end is $parent_end, end without hash is $end_without_hash";

            if($parent_end == $end_without_hash)//we have an anchor, and it points to parent_page
            {
                    echo "same page anchor\n";
                  return '';
            }
        }

        //the first element is always empty, so remove it
        array_shift($parent_file_parts);
            echo "dumping after shift\n";
            var_dump($parent_file_parts);

        if(strpos($input_link, '/') !== false)
        {
            $input_link = explode('/', $input_link);
                echo "dumping after input link explode\n";
                var_dump($input_link);
        }

        //get the number of pieces we want to keep from the parent file path
        $working_count = count($parent_file_parts) - $relative_dirs_count;
        $working_count = $working_count - 1;

        //start at zero, only include elements up to $working_count
        $parent_file_parts = array_slice($parent_file_parts, 0, $working_count);
            echo "dumping after slide up to working_count\n";
            var_dump($parent_file_parts);

        /*if(!empty($same_page))
        {
                $resolved_end = $parent_end . $input_link;
                    echo "resolved end is $resolved_end \n";

                $parent_file_parts[] = $resolved_end;
                $resolved_link = $parent_file_parts;
        }*/
//        else //knock the relative directories off the path, then merge parent path and current page
//        {
            if($relative_dirs_count !== 0)
            {
                $input_link = array_slice($input_link, $relative_dirs_count);
                    echo "dumping input_link after slice\n";
                    var_dump($input_link);

                $resolved_link = array_merge($parent_file_parts, $input_link);
            }
            else
            {
                if(is_string($input_link))
                {
                    $parent_file_parts[] = $input_link;
                }
                else
                {
                    $parent_file_parts = array_merge($parent_file_parts, $input_link);
                }
                $resolved_link = $parent_file_parts;
            }

//        }

        if(is_array($resolved_link))
        {
            $link = implode('/', $resolved_link);
            $resolved_link = $link;

            var_dump($resolved_link);
        }

            echo "dumping resolved_link\n";
            var_dump($resolved_link);

        return $resolved_link;
    }

    public function wikiName($input)
    {
        echo "in wikiName, link is $input \n";

        if(strpos($input, '#') !== false)
        {
            $anchor_link = true;
        }

        $wiki_name = explode('/', $input);

        //to ['subdir', 'subdir', 'pagename'] (only the last three elements are needed)
        $wiki_name = array_slice($wiki_name, -3);
            echo "wiki_name after slice last 3\n";
            var_dump($wiki_name);

        if(!empty($anchor_link))
        {
                echo "is anchor in wiki_name\n";

            $last_element = array_pop($wiki_name);
                var_dump($last_element);

            $wiki_name = array_map('strtolower', $wiki_name);
            $wiki_name = array_map('ucfirst', $wiki_name); //ucfirst the rest of wiki_name
                echo "wikiName in anchor after ucfirst\n";
                var_dump($wiki_name);

            $last_element = explode('#', $last_element);
                var_dump($last_element);

            $anchor = $last_element[1]; //grab #anchor
            $last_element = strtolower($last_element[0]); //lower filename.ext but leave #anchor untouched
            $last_element = ucfirst($last_element); //ucfirst it
                var_dump($last_element, $anchor);

            $last_element = explode('.', $last_element);
            if(!empty($last_element[1])) //grab '.ext'
            {
                $ext = $last_element[1];
            }
            else
            {
                $ext = '';
            }
            var_dump($last_element, $ext);

            $wiki_name[] = $last_element[0]; //add filename to wiki_name
                var_dump($wiki_name);

            $wiki_name = implode($wiki_name);

            $wiki_name = $wiki_name . '#' . $anchor;
        }
        else
        {
            //calls the ucfirst function on each element of the array
            $wiki_name = array_map('strtolower', $wiki_name);
            $wiki_name = array_map('ucfirst', $wiki_name);
//                echo "dump after ucfirst\n";
//                var_dump($wiki_name);

            $wiki_name = implode($wiki_name);
            $wiki_name = explode('.', $wiki_name);
            $ext = array_pop($wiki_name); // grab the extension
            $wiki_name = $wiki_name[0]; //grab the wikiName
//                var_dump($wiki_name, $ext);
        }

        return array($wiki_name, $ext);
    }

    protected function filterPage($page)
    {
        $lines = file($page);


        // remove webbot
            $callback = function ($input)
            {
                $counter = 0;

                if(strpos($input, 'startspan') !== false)
                {
                    $counter++;
                }

                if (strpos($input, 'endspan') !== false)
                {
                    $counter++;
                }

                if($counter > 0)
                {
    //                echo "counter is $counter";

                   /* if($counter > 1)
                    {
                        return false;
                    }*/
                }

                return $counter;
            };

            $starts_and_ends = array_filter($lines, $callback);
            $count = count($starts_and_ends);

            if($count & 1)
            {
                //start and endspan on same line, remove all these now
                foreach($starts_and_ends as $key => $item)
                {
                    if((strpos($item, 'start') !== false) && (strpos($item, 'end') !== false))
                    {
                            echo "we have both start and endspan on the same line, dumping line\n";
                            var_dump($item);
                            echo "key is $key\n";

                        unset($starts_and_ends[$key], $lines[$key]);
                    }
                }
            }

            $keys = array_keys($starts_and_ends);

    //            var_dump($starts_and_ends);
    //            var_dump($keys);
    //            echo $count;

            for($i = 0; $i < count($keys) ; $i += 2)
            {
                    echo "start of loop\n";

                $start = $keys[$i];
                $end = $keys[$i + 1];

    //                echo "start is $start \n";
    //                echo "end is $end \n";
    //                echo "length is $length \n";

                for($a = $start ; $a <= $end; $a++)
                {
                    unset($lines[$a]);
                }
            }
        // end remove webbot

        //var_dump($lines);
        $output = implode($lines);

        $output = str_replace('&nbsp;', '', $output);
        echo "dumping output\n";
        var_dump($output);
        file_put_contents('output', $output);
        return $output;
    }
}
