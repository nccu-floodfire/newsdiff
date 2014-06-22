<?php

class NewInfoRow extends Pix_Table_Row
{
}

class NewsInfo extends Pix_Table
{
    public function init()
    {
        $this->_name = 'news_info';
        $this->_primary = array('news_id', 'time');
        $this->_rowClass = 'NewInfoRow';

        $this->_columns['news_id'] = array('type' => 'int');
        $this->_columns['time'] = array('type' => 'int');
        $this->_columns['title'] = array('type' => 'text');
        $this->_columns['body'] = array('type' => 'text');
    }

    public static function insert($data) {
        $url = 'http://news-ckip.source.today/push_to_ckip';
        $fields = array(
            'text' => urlencode($data['title'] . ' ' . $data['body']),
        );
        foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
        rtrim($fields_string, '&');

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, count($fields));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);

        $result = curl_exec($ch);
        curl_close($ch);

        return parent::insert($data);
    }
}
