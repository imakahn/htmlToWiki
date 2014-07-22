<?php
class files{
    public $html_array, $img_array, $htm_extensions, $img_extensions, $gallery_img_array;

    function __construct($directory = null)
    {
        $this->htm_extensions = ['htm','html','HTM','HTML'];
        $this->img_extensions = ['jpg','JPG','jpeg','JPEG','gif','GIF','png','PNG'];

        if(empty($directory))
        {
            //$directory = '/home/andrew/web/working/manifesto';
            $directory = '/home/andrew/web/working/manifesto';

            $this->scanFiles($directory);

            file_put_contents('img_array', json_encode($this->img_array));
            file_put_contents('html_array', json_encode($this->html_array));
           // var_dump($this->img_array);
            //var_dump($this->html_array);
        }
    }

    function scanFiles($dir, $gallery = null)
    {
        $files = scandir($dir);

        $prev_file = '';

        foreach($files as $file)
        {
            if(is_file("$dir/$file"))
            {
//                    echo "\nstart of loop\n";

                $working_file = "$dir/$file";
                $file_info = pathinfo($working_file);
                $ext = $file_info['extension'];
                $basename = $file_info['basename'];
                $current_no_ext = str_replace(".$ext", '', $basename);
                $prev_t = $prev_file . 't';


                /* ============ *
                *   Filtration  *
                *  ============ */

                //wiki files from old script
                if(($ext == 'wiki') || ($ext == 'wiki2') || ($ext == 'htm4wiki') || ($ext == 'html4wiki') || ($ext == 'HTM4wiki'))
                {
                    unlink($working_file);
                }

                //thumbnails
                if(strpos($working_file, '_small') !== false)
                {
                    $prev_file = $current_no_ext;
                    continue;
                }
                elseif($prev_t == $current_no_ext)
                {
                    $prev_file = $current_no_ext;
                    continue;
                }

                //photoshop gallery chaos
                if($basename == 'index.htm') //we're in a photoshop image gallery dir
                {
                    //echo $working_file . PHP_EOL;

                    $gallery = true;
                    $index_wiki_name = $this->wikiName($working_file)[0];
                }


                /* =============== *
                 * Load to arrays  *
                 * ============== */

                if(in_array($ext, $this->img_extensions))
                {
                    $img_wiki_name = $this->wikiName($working_file);
                    $img_wiki_name = $img_wiki_name[0] . '.' . $img_wiki_name[1];

                    $this->img_array[] = ['name' => $working_file, 'wiki_name' => $img_wiki_name];

                    if(!empty($gallery))
                    {
                        if(stripos($img_wiki_name, 'gif') === false)
                        {
                            $this->gallery_img_array[] = $img_wiki_name;
                        }
                    }
                }
                elseif(in_array($ext, $this->htm_extensions))
                {
                    if(!empty($gallery))
                    {
                        continue;
                    }
                    if(stripos($basename, 'thumbnail') !== false)
                    {
                        continue;
                    }

                    $html_wiki_name = $this->wikiName($working_file)[0];

                    $this->html_array[] = ['name' => $working_file, 'wiki_name' => $html_wiki_name];
                }

                $prev_file = $current_no_ext;
            }
        }

        foreach($files as $file)
        {
            if($file != '.' && $file != '..')
            {
                if(is_dir("$dir/$file"))
                {
                    $working_dir = "$dir/$file";

                        //  echo "$file" . PHP_EOL;
                        //echo "is dir!\n\n";

                    if(!empty($gallery))
                    {
                        if($file !== 'images')
                        {
                            continue;
                        }
                        else
                        {
//                                echo $file . PHP_EOL;
                            $this->scanFiles($working_dir, $gallery);

                            if(!empty($index_wiki_name))
                            {
                                $index_output = $this->createIndex();
//                                echo $index_output;

                                file_put_contents("out/$index_wiki_name", $index_output);
                            }
                            else
                            {
                                echo "ERROR index_wiki_name doesn't exist!\n";
                            }
                        }
                    }
                    else
                    {
                        $this->scanFiles($working_dir);
                    }
                }
            }
        }
    }

    public function createIndex()
    {
        $output = '<gallery>';

//        echo "in createIndex\n";
//        var_dump($this->gallery_img_array);

        foreach($this->gallery_img_array as $img)
        {
            $output .= 'File:' . $img . PHP_EOL;
        }

        $output .= '</gallery>';

        unset($this->gallery_img_array);

        return $output;
    }

    public function wikiName($input)
    {
//        echo "in wikiName, link is $input \n";

        if(strpos($input, '#') !== false)
        {
            $anchor_link = true;
        }

        $wiki_name = explode('/', $input);

        //to ['subdir', 'subdir', 'pagename'] (only the last three elements are needed)
        $wiki_name = array_slice($wiki_name, -3);
//            echo "wiki_name after slice last 3\n";
//            var_dump($wiki_name);

        if(!empty($anchor_link))
        {
//                echo "is anchor in wiki_name\n";

            $last_element = array_pop($wiki_name);
//                var_dump($last_element);

            $wiki_name = array_map('ucfirst', $wiki_name); //ucfirst the rest of wiki_name
//                var_dump($wiki_name);

            $last_element = explode('#', $last_element);
//                var_dump($last_element);

            $anchor = $last_element[1]; //grab #anchor
            $last_element = strtolower($last_element[0]); //lower filename.ext but leave #anchor untouched
            $last_element = ucfirst($last_element); //ucfirst it
//                var_dump($last_element, $anchor);

            $last_element = explode('.', $last_element);
            $ext = $last_element[1]; //grab '.ext'
//                var_dump($last_element, $ext);

            $wiki_name[] = $last_element[0]; //add filename to wiki_name
//                var_dump($wiki_name);

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

}
