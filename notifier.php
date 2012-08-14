<?php
require_once 'simple_html_dom.php';
require_once 'sqlite.class.php';
require_once 'sqlite.setup.php';
require_once 'config.php';

/* This URL might change so we put it in an easy to access var */
$developer_url = 'http://www.magentocommerce.com/magento-connect/developer/DEVELOPER_NAME';

/* Loop through the developer profile page to find all extensions by this developer */
$extension_urls = array();
$dom = str_get_html(file_get_contents(str_replace('DEVELOPER_NAME',$developer_name,$developer_url)));
$pages = 0;

/* Find the number of pages with extensions */
foreach($dom->find('div#category_products_list div.pager div.pager-pages a') as $page) {
    $pages++;
}
if(!$pages) {
    /* If the developer has one page with extensions */
    foreach($dom->find('div#category_products_list h2.featured-extension-title a') as $a) {
        $extension_urls[] = $a->href;
    }
} else {
    /* In the case of multiple pages with extensions */
    for($i=1;$i<=$pages;$i++) {
        $dom = str_get_html(file_get_contents(str_replace('DEVELOPER_NAME',$developer_name,$developer_url).'?p='.$i));
        foreach($dom->find('div#category_products_list h2.featured-extension-title a') as $a) {
            $extension_urls[] = $a->href;
        }
    }
}

$mailLines = array();

/* Loop through all extension pages to see how many reviews there are */
foreach($extension_urls as $extension_url) {
    $dom = str_get_html(file_get_contents($extension_url));
    foreach($dom->find('dt.tab-reviews') as $reviewtab) {
        $result = preg_replace("/[^0-9]/","", $reviewtab->innertext);
    }
    foreach($dom->find('h1[itemprop=name]') as $name) {
        $name = $name->innertext;
    }
    try {
        $results = $db->query("SELECT * FROM entries WHERE `extension_url` = '".$extension_url."'")->fetchArray(SQLITE3_ASSOC);
    } catch(Exception $e) {
        $mailLines[] = "SQL Error: ".$e->getMessage();
        $mail = true;
    }

    /* If no result is found in the database, enter the value */
    if(!$results) {
        try {
            $db->exec("INSERT INTO entries VALUES (null,'".$extension_url."', '".$result."')");
        } catch(Exception $e) {
            $mailLines[] = "SQL Error: ".$e->getMessage();
            $mail = true;
        }
        $mail = true;
    } else {
        /* If a result is found, check if the result differs from the previous run. If so; add it to the email lines array */
        if($results['reviews']!=$result) {
            try {
                $db->exec("UPDATE entries SET reviews = '".$result."' WHERE extension_url = '".$extension_url."'");
            } catch(Exception $e) {
                $mailLines[] = "SQL Error: ".$e->getMessage();
                $mail = true;
            }
            $mail = true;
        } else {
            $mail = false;
        }
    }

    if($mail) {
        $line = $name." has ".$result." review(s) (".($result-$results['reviews'])." new); ".$extension_url."\n";
        $mailLines[] = $line;
    } else {
        $line = $name." has ".$result." review(s)\n";
    }
    echo $line;
}

/* If there are any email lines, put them together in one email and send it off! */
if(count($mailLines)) {
    $headers  = 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
    $headers .= 'From: '.$developer_name.' <'.$fromEmail.'>' . "\r\n";

    mail($toEmail,'Magento Connect Review updates',implode($mailLines,'<br />'),$headers);
}