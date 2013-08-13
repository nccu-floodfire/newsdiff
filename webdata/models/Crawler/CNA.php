<?php

class Crawler_CNA
{
    public static function crawl()
    {
        // http://www.cna.com.tw/News/aCN/201308130087-1.aspx
        // http://www.cna.com.tw/Topic/Popular/3907-1/201308130021-1.aspx
        $content = Crawler::getBody('http://www.cna.com.tw/');

        preg_match_all('#/(News|Topic/Popular)/[^/]*/\d+-\d+\.aspx#', $content, $matches);
        foreach ($matches[0] as $link) {
            try {
                $url = 'http://www.cna.com.tw/' . $link;
                News::insert(array(
                    'url' => $url,
                    'url_crc32' => crc32($url),
                    'created_at' => time(),
                    'last_fetch_at' => 0,
                ));
            } catch (Pix_Table_DuplicateException $e) {
            }
        }

    }

    public static function parse($body)
    {
        if (preg_match('/<title>404<\/title>/', $body)) {
            $ret = new StdClass;
            $ret->title = '404';
            $ret->body = '404';
            return $ret;
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        @$doc->loadHTML($body);
        $ret = new StdClass;

        foreach ($doc->getElementsByTagName('div') as $div_dom) {
            if ($div_dom->getAttribute('class') == 'news_content') {
                $ret->title = $div_dom->getElementsByTagName('h1')->item(0)->nodeValue;
                foreach ($div_dom->getElementsByTagName('div') as $child_div_dom) {
                    if ($child_div_dom->getAttribute('class') == 'box_2') {
                        $ret->body = trim(Crawler::getTextFromDom($child_div_dom));
                        break;
                    }
                }
                break;
            }
        }

        return $ret;
    }
}