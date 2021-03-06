<?php

include_once APP_PATH . '/extensions/channels/taobao/sdk/TopClient.php';
include_once APP_PATH . '/extensions/channels/taobao/sdk/request/ItemGetRequest.php';
include_once APP_PATH . '/extensions/channels/taobao/sdk/request/ShopGetRequest.php';
include_once APP_PATH . '/extensions/channels/taobao/sdk/request/TaobaokeItemsDetailGetRequest.php';
class Channel_taobao
{
    private $channel = 'taobao';
    protected $info;
    protected $client;
    protected $item_get_request;
    function __construct($info)
    {
        $this->info = $info;
        $this->client = new TopClient();
        $this->item_get_request = new ItemGetRequest();
    }
    public function fetch_goodinfo($pid)
    {
        $this->client->appkey = $this->info['APPKEY'];
        $this->client->secretKey = $this->info['APPSECRET'];
        $this->item_get_request->setFields("detail_url,title,nick,pic_url,item_img.url,price");
        $this->item_get_request->setNumIid($pid);
        $item_resp = $this->client->execute($this->item_get_request);
        if (!isset($item_resp->item))
            return false;
        $item = (array)$item_resp->item;
        $item_imgs = (array)($item_resp->item->item_imgs);
        $imgs = array();
        $single = $item_imgs['item_img'];
        if ($single->url)
        {
            $imgs[] = (array)$single;
        }
        else
        {
            foreach ($item_imgs['item_img'] as $img_obj)
            {
                $img_arr = (array)$img_obj;
                $imgs[] = $img_arr;
            }
        }
        $result = array();
        $result['name'] = $item['title'];
        $result['price'] = $item['price'];
        $result['item_imgs'] = $imgs;
        $result['orgin_img_url_small'] = $item['pic_url'] . '_200x200.jpg';
        $result['orgin_img_url'] = $item['pic_url'];
        $result['orgin_url'] = $item['detail_url'];
        $result['shop_name'] = $item['nick'];
        return $result;
    }
    public function get_promotion_url($itemid)
    {
        if ($this->info['PROMOTION_ID'])
        {
            $req = new TaobaokeItemsDetailGetRequest();
            $req->setFields("click_url,shop_click_url");
            $req->setNumIids($itemid);
            $req->setPid($this->info['PROMOTION_ID']);
            $resp = $this->client->execute($req);
            if (isset($resp->taobaoke_item_details))
            {
                $promotion = (array)$resp->taobaoke_item_details->taobaoke_item_detail;
                return $promotion;
            }
        }
    }
    public function get_item_id($url)
    {
        $url_parse = parse_url($url);
        if (isset($url_parse['query']))
        {
            parse_str($url_parse['query'], $params);
            if (isset($params['id']))
                $item_id = $params['id'];
            elseif (isset($params['item_id']))
                $item_id = $params['item_id'];
        }
        return $item_id;
    }
}

?>