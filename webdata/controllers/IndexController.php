<?php

class IndexController extends Pix_Controller
{
    private function _parseQuery($string, $field_name = 'ni.title')
    {
        if ($string === "*") {
            return "";
        }
        $filterArr = array('(', ')', '&#34;');
        $string = trim(str_replace($filterArr, "", $string));
        $queryArr = explode("|", $string);

        $baseSQL = " $field_name LIKE ";
        $output = " AND (";
        foreach ($queryArr as $key => $value) {
            $value = trim($value);
            $output .= $baseSQL . '"%' . $value . '%"';
            if ($key < count($queryArr) - 1) {
                $output .= " OR ";
            }
        }
        $output .= ") ";
        return $output;
    }

    private function _initSearch($is_search = false)
    {
        $this->view->search_array = array();
        $this->view->news_array = array();
        $queryTitle = filter_input(INPUT_GET, 'q_title', FILTER_SANITIZE_SPECIAL_CHARS);
        $queryTimeStart = filter_input(INPUT_GET, 'q_timestart', FILTER_SANITIZE_SPECIAL_CHARS);
        $queryTimeEnd = filter_input(INPUT_GET, 'q_timeend', FILTER_SANITIZE_SPECIAL_CHARS);
        $querySource = filter_input(INPUT_GET, 'q_source', FILTER_VALIDATE_INT);
        $queryLimit = filter_input(INPUT_GET, 'q_limit', FILTER_VALIDATE_INT);

        $now = time();
        $ts_start = $now - 86400;
        $ts_end = $now;
        $enable_search = false;
        if (!empty($queryTimeStart)) {
            $ts_start = strtotime($queryTimeStart);
            $enable_search = true;
        }
        if (!empty($queryTimeEnd)) {
            $ts_end = strtotime($queryTimeEnd) + 86399;
            $enable_search = true;
        }

        if (!empty($queryTitle)) {
            $enable_search = true;
        }

        if (empty($queryLimit)) {
            $queryLimit = 0;
        }

        if ($is_search) {
            $enable_search = true;
        }
        $this->view->query_title = $queryTitle;
        $this->view->query_time_start = $queryTimeStart;
        $this->view->query_time_end = $queryTimeEnd;

        return array($ts_start, $ts_end, $queryTitle, $querySource, $queryLimit, $enable_search);
    }
    public function indexAction()
    {
        $is_export = false;
        $iscsv = $this->_initCsv();
        $issma = $this->_initSma();
        if ($iscsv || $issma) {
            $is_export = true;
        }
        list($ts_start, $ts_end, $queryTitle, $querySource, $queryLimit, $enable_search) = $this->_initSearch();
        $resArr = array();

        if ($enable_search) {
            $resArr = $this->_searchNews($ts_start, $ts_end, $queryTitle, null, $is_export);
            $this->view->search_array = $resArr;
        } else {
            $this->view->news_array = News::search(1)->order('last_fetch_at DESC')->limit(100);
        }
        if ($enable_search && $iscsv) {
            $this->_handelCsv($resArr);
        }
        if ($enable_search && $issma) {
            $this->_handleSma($resArr);
        }
    }
    private function _searchNews($ts_start, $ts_end, $queryTitle, $source_id = null, $queryLimit = 0, $is_export = false)
    {
        $resArr = array();
        $db = News::getDb();
        $source_id_statement = "";
        $limit_statement = "";
        $addon_select = "";
        if ($is_export) {
            $addon_select = ", ni.body, n.id, n.url";
        }
        if ($source_id !== null) {
            $source_id_statement = " AND n.source = $source_id ";
        }

        if ($queryLimit !== 0) {
            $limit_statement = " LIMIT $queryLimit ";
        }
        $sql = <<<EOF
SELECT n.id, ni.title, n.source, ni.time $addon_select FROM
news_info as ni
LEFT JOIN news as n
ON ni.news_id = n.id
WHERE ni.time BETWEEN $ts_start AND $ts_end
$source_id_statement
$limit_statement
EOF;


        if (!empty($queryTitle)) {
            $sql .=  $this->_parseQuery($queryTitle); // FIXME SQL injection
        }

        $res = $db->query($sql);

        while ($row = $res->fetch_assoc()) {
            $resArr [] = $row;
        }
        return $resArr;
    }

    private function _initCsv()
    {
        $is_export = filter_input(INPUT_GET, 'iscsv', FILTER_VALIDATE_INT);
        return $is_export;
    }

    private function _initSma()
    {
        $issma = filter_input(INPUT_GET, 'issma', FILTER_VALIDATE_INT);
        return $issma;
    }

    private function _handelCsv($input)
    {
        //$file = "text";
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=export.csv');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        //header('Content-Length: ' . strlen($file));
        //echo $file;
        $sourceMap = News::getSources();
        $out = fopen('php://output', 'w');
        fputs($out, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));
        foreach ($input as $arr) {
            $arr["time"] = date("Y-m-d: H:i:s", $arr["time"]);
            $arr["source"] = $sourceMap[$arr["source"]];
            fputcsv($out, $arr);
        }
        fclose($out);
        exit;
    }

    private function _handleSma($input)
    {
        //$file = "text";
        //header('Content-Description: File Transfer');
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename=export.json');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        //header('Content-Length: ' . strlen($file));
        //echo $file;
        $outputArr = array();
        echo '{"documents":';
        $sourceMap = News::getSources();
        //$out = fopen('php://output', 'w');
        $i = 0;
        foreach ($input as $arr) {
            $i++;
            $newArr['Id'] = $arr["id"];
            $newArr['Url'] = $arr["url"];

            $newArr["Published"] = date("Y-m-d H:i:s", $arr["time"]);
            $newArr["SubjectHtml"] = $arr["title"];
            $newArr["TextHtml"] = $arr["body"];
            $newArr["DocumentType"] = $sourceMap[$arr["source"]];
            $newArr["SiteUrl"] = $arr["url"];
            $newArr["SiteName"] = $sourceMap[$arr["source"]];
            $newArr["Language"] = "Chinese - Traditional";
            $outputArr []= $newArr;
            if ($i >= 100) {
                $i = 0;
                echo json_encode($outputArr, JSON_UNESCAPED_UNICODE);
                $outputArr = array();
            }
            //$arr["source"] = $sourceMap[$arr["source"]];
        }
        echo json_encode($outputArr, JSON_UNESCAPED_UNICODE);
        echo "}";
        //fclose($out);
        exit;
    }

    public function logAction()
    {
        list(, /*index*/, /*log*/, $news_id) = explode('/', $this->getURI());

        $this->view->news = News::find(intval($news_id));
        if (!$this->view->news) {
            return $this->redirect('/');
        }
    }

    public function sourceAction()
    {
        $is_export = false;
        $iscsv = $this->_initCsv();
        $issma = $this->_initSma();
        if ($iscsv || $issma) {
            $is_export = true;
        }
        list($ts_start, $ts_end, $queryTitle, $querySource, $queryLimit, $enable_search) = $this->_initSearch();
        list(, /*index*/, /*source*/, $source_id) = explode('/', $this->getURI());
        if ($enable_search) {
            $resArr = $this->_searchNews($ts_start, $ts_end, $queryTitle, $source_id, $is_export);
            $this->view->search_array = $resArr;
        } else {
            $sources = News::getSources();
            if (!array_key_exists(intval($source_id), $sources)) {
                return $this->redirect('/');
            }
            $this->view->news_array = News::search(array('source' => intval($source_id)))->order('last_fetch_at DESC')->limit(100);
        }
        if ($enable_search && $iscsv) {
            $this->_handelCsv($resArr);
        }
        if ($enable_search && $issma) {
            $this->_handleSma($resArr);
        }
        $this->view->source_id = intval($source_id);
        return $this->redraw('/index/index.phtml');
    }

    public function searchAction()
    {
        $queryLink = @$_GET['q_link'];
        if (!empty ($queryLink) && $news = News::findByURL($queryLink)) {
            return $this->redirect('/index/log/' . $news->id);
        }

        // 處理 http://foo.com/news/2013/1/23/我是中文標題-123456 這種網址
        $terms = explode('/', $_GET['q']);
        $last_term = array_pop($terms);
        array_push($terms, urlencode($last_term));
        if ($news = News::findByURL(implode('/', $terms))) {
            return $this->redirect('/index/log/' . $news->id);
        }

        // 處理 http://foo.com/news/2013/1/23/news.php?category=中文分類&id=12345
        $url = $_GET['q'];
        $url = preg_replace_callback('/=([^&]*)/', function ($m) {
            return '=' . urlencode($m[1]);
        }, $url);
        if ($news = News::findByURL($url)) {
            return $this->redirect('/index/log/' . $news->id);
        }

        return $this->alert('not found', '/');
    }

    public function healthAction()
    {
        header('Content-Type: text/plain');

        $ret = array();
        $check_time = 30; // 幾分鐘沒有從列表抓到任何新聞就要警告

        $sources = News::getSources();
        foreach ($sources as $id => $name) {
            if (date('H') > 8 and KeyValue::get('source_update-' . $id) < time() - $check_time * 60) {
                // 早上八點以後才會確認這個
                $ret[] = "{$name}({$id}) 超過 {$check_time} 分鐘沒有抓到新聞";
                continue;
            }

            if (KeyValue::get('source_insert-' . $id) < time() - 86400) {
                $ret[] = "{$name}({$id}) 超過一天沒有抓到新的新聞";
                continue;
            }
        }
        echo implode("\n", $ret);

        $now = time();
        $source_ids = News::search("created_at > $now - 86400 AND last_fetch_at < $now - 3600")->toArray('source');
        $source_ids = array_count_values($source_ids);
        $count = array_sum($source_ids);
        if ($count > 1000 or (array_key_exists('test', $_GET) and $_GET['test'])) {
            $new_count = count(News::search("created_at > $now - 86400 AND last_fetch_at = 0"));
            echo "\n目前累積要更新新聞數有 {$count} 則(新資料: {$new_count} 筆)\n";
            foreach ($source_ids as $source => $source_count) {
                echo "{$sources[$source]}: {$source_count}\n";
            }
            echo "正在抓: " . KeyValue::get('Crawling');
        }
        exit;
    }

    public function SmaWebServiceAction()
    {
        $is_export = false;
        $issma = true;
        if ($issma) {
            $is_export = true;
        }
        list($ts_start, $ts_end, $queryTitle, $querySource, $queryLimit, $enable_search) = $this->_initSearch(true);
        $resArr = array();

        if ($enable_search) {
            $resArr = $this->_searchNews($ts_start, $ts_end, $queryTitle, $querySource, $queryLimit, $is_export);
            $this->view->search_array = $resArr;
        }
        if ($enable_search && $issma) {
            $this->_handleSma($resArr);
        }
    }
}
