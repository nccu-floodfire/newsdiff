<?php

class IndexController extends Pix_Controller
{
	private function _initSearch()
	{
		$this->view->search_array = array();
		$this->view->news_array = array();
		$queryTitle = filter_input(INPUT_GET, 'q_title', FILTER_SANITIZE_SPECIAL_CHARS);
		$queryTimeStart = filter_input(INPUT_GET, 'q_timestart', FILTER_SANITIZE_SPECIAL_CHARS);
		$queryTimeEnd = filter_input(INPUT_GET, 'q_timeend', FILTER_SANITIZE_SPECIAL_CHARS);
		$now = time();
		$ts_start = $now - 86400;
		$ts_end = $now;
		if (!empty($queryTimeStart)) {
			$ts_start = strtotime($queryTimeStart);
		}
		if (!empty($queryTimeEnd)) {
			$ts_end = strtotime($queryTimeEnd);
		}
		$this->view->query_title = $queryTitle;
		$this->view->query_time_start = $queryTimeStart;
		$this->view->query_time_end = $queryTimeEnd;

		return array($ts_start, $ts_end, $queryTitle);
	}
    public function indexAction()
    {
	    list($ts_start, $ts_end, $queryTitle) = $this->_initSearch();

	    if (!empty($queryTitle)) {
		    $resObj = $this->_searchNews($ts_start, $ts_end, $queryTitle);
		    $this->view->search_array = $resObj;
	    } else {
		    $this->view->news_array = News::search(1)->order('last_fetch_at DESC')->limit(100);
	    }
    }

	private function _searchNews($ts_start, $ts_end, $queryTitle, $source_id = null)
	{
		$resObj = array();
		$db = News::getDb();
		$source_id_statement = "";
		if ($source_id !== null) {
			$source_id_statement = " AND n.source = $source_id ";
		}
		$sql = <<<EOF
SELECT n.id, ni.title, n.source, ni.time FROM
news_info as ni
LEFT JOIN news as n
ON ni.news_id = n.id
WHERE ni.time BETWEEN $ts_start AND $ts_end
$source_id_statement
AND ni.title LIKE '%$queryTitle%'
EOF;
		$res = $db->query($sql);

		while ($obj = $res->fetch_object()) {
			$resObj [] = $obj;
		}
		return $resObj;
	}

	public function exportcsvAction()
	{
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
	    list($ts_start, $ts_end, $queryTitle) = $this->_initSearch();
	    list(, /*index*/, /*source*/, $source_id) = explode('/', $this->getURI());
	    var_dump($source_id);
	    if (!empty($queryTitle)) {
		    $resObj = $this->_searchNews($ts_start, $ts_end, $queryTitle, $source_id);
		    $this->view->search_array = $resObj;
	    } else {

		    $sources = News::getSources();
		    if (!array_key_exists(intval($source_id), $sources)) {
			    return $this->redirect('/');
		    }

		    $this->view->news_array = News::search(array('source' => intval($source_id)))->order('last_fetch_at DESC')->limit(100);
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
}
