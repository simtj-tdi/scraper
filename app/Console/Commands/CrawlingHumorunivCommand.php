<?php

namespace App\Console\Commands;

use App\g5_board;
use App\g5_board_new;
use App\g5_write_humor;
use Carbon\Carbon;
use Goutte\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CrawlingHumorunivCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crawling:humoruniv';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Log::info("crawling_start");

        $listUrl = "/list.html?table=pick&pg=0";
        $baseUrl = "http://web.humoruniv.com/board/humor";

        $listLinks = $this->getBoardList($baseUrl . $listUrl);

        $contents = $this->getBaordContents($listLinks);
        $filterContents = $this->contentsException($contents);
        $this->imageDownload($filterContents);
        $newContents = $this->imageUrlChange($filterContents);

        $this->baordWrite($newContents);


        return 0;
    }

    public function getClient()
    {
        $client = new Client();

        return $client;
    }

    public function getBoardList($baseUrl)
    {
        $client = $this->getClient();
        $crawler = $client->request('GET', $baseUrl);

        $hyperlinks  = $crawler->filter('a[href^=\'read.html?table=pick&pg=0\']')
            ->reduce(function ($node) {
                return $node->text() != null;
            })
            ->each(function ($node) {
                return [$node->text(), "http://web.humoruniv.com/board/".$node->attr('href')];
            });

        return $hyperlinks;
    }

    public function getBaordContents($listLinks)
    {
        $client = $this->getClient();
        $data = array_map(function ($item) use ($client) {

            $crawler = $client->request('GET', $item[1]);

            $titleText = $crawler->filter('span[id^=\'ai_cm_title\']')->text();
            $outHtml = $crawler->filter('wrap_copy')->outerHtml();
            $resourceText = $crawler->filter('div[class^=\'cbay_text_new\']')->text();

            $imageSrc = array();
            $i = 0;
            $crawler->filter('wrap_copy[id^=\'wrap_copy\'] img')->each(function ($node) use (&$imageSrc, &$i) {
               if (!preg_match('/images/',$node->attr('src'), $matches)) {
                   $imageSrc[$i] = $node->attr('src');
                   $i++;
               }
             });

            //Log::info("linke ::: ". $item[1] . "   html :: ".$outHtml);
            return ['title' => $titleText, 'imageSrc'=> $imageSrc, 'html' => $outHtml, 'resourceText' => $resourceText];
        }, $listLinks);

        return $data;
    }

    public function contentsException($contents)
    {
        $filtered = array_filter($contents, function ($val, $key) {

                return !preg_match('/show_hidden_img/', $val['html'] , $matches)
                    && !preg_match('/comment_mp4_expand/', $val['html'] , $matches)
                    && !preg_match('/ic_re.png/', $val['html'] , $matches)
                    && substr_count($val['html'], '&#') > 50
                    && $this->overlap_check($val['title'])
                    ;
        }, ARRAY_FILTER_USE_BOTH);

        return $filtered;
    }


    public function imageDownload($filterContents)
    {
        $storage_path = env("STORAGE_PATH");

        array_map(function ($item) use ($storage_path) {
            array_map(function ($v) use ($storage_path) {

                if (preg_match('/\?SIZE=/', $v, $matches)) {
                    $tempString = explode('?',$this->proper_parse_str(parse_url($v)['query'])['url']);
                    $v = $tempString[0];
                }

                $file = file_get_contents($v);
                file_put_contents($storage_path.basename($v),$file) ;
            }, $item['imageSrc']);
        }, $filterContents);
    }

    function imageUrlChange($filterContents)
    {
        $localBase = env("IMAGE_URL") . "data/file/crawling/";
        $localUrl = array (
            $localBase,
            $localBase,
        );

        $originalUrl = array (
            "/http:\/\/down.humoruniv.com\/\/hwiparambbs\/data\/editor\/pdswait\//",
            "/http:\/\/down.humoruniv.com\/hwiparambbs\/data\/pick\//",
        );


        $newContents = array_map(function ($item) use ($localUrl, $originalUrl) {
            $newHtml = preg_replace("/http:\/\/t.huv.kr\/thumb_crop_resize.php\?url=|\?SIZE=[0-9]{2,4}x[0-9]{2,4}/", "", $item['html']);
            $newHtml = preg_replace($originalUrl, $localUrl, $newHtml);

            return ['title' => $item['title'], 'newHtml' => $newHtml, 'resourceText' => $item['resourceText']];
        }, $filterContents);

        return $newContents;
    }

    function baordWrite($newContents)
    {

        foreach ($newContents as $k => $v) {
            $now = Carbon::createFromFormat('Y-m-d H:i:s', Carbon::now());

            $insert = array(
                'wr_num' => $this->get_next_num(),
                'wr_reply' => '',
                'wr_comment' => 0,
                'wr_comment_reply' => '',
                'ca_name' => '',
                'wr_option' => 'html1',
                'wr_subject' => $v['title'],
                'wr_content' =>  $v['newHtml'],
                'wr_link1' => '',
                'wr_link2' => '',
                'wr_link1_hit' => 0,
                'wr_link2_hit' => 0,
                'wr_hit' => 0,
                'wr_good' => 0,
                'wr_nogood' => 0,
                'mb_id' => 'admin',
                'wr_password' => '*D5B5D80788B9B48E9ABCD04F08CA7267C1F91017',
                'wr_name' => 'manabom',
                'wr_email' => 'admin@domain.com',
                'wr_homepage' => '',
                'wr_datetime' => $now,
                'wr_last' => $now,
                'wr_ip' => '127.0.0.1',
                'wr_facebook_user' => '',
                'wr_twitter_user' => '',
                'wr_1' => '',
                'wr_2' => '',
                'wr_3' => $v['resourceText'],
                'wr_4' => '',
                'wr_5' => '',
                'wr_6' => '',
                'wr_7' => '',
                'wr_8' => '',
                'wr_9' => '',
                'wr_10' => '',
                'as_type' => 0,
                'as_img' => 0,
                'as_publish' => 0,
                'as_update' => $now,
                'as_extra' => 0,
                'as_extend' => 0,
                'as_level' => '10',
                'as_down' => 0,
                'as_view' => 0,
                'as_re_mb' => '',
                'as_re_name' => '',
                'as_tag' => '',
                'as_map' => '',
                'as_icon' => '',
                'as_thumb' => '',
                'as_video' => '',
            );

            $insertResult = g5_write_humor::create($insert);
            $wr_id = $insertResult->id;

            $update = array(
                'wr_parent' => $wr_id
            );
            $updateResult = g5_write_humor::where('wr_id','=',$wr_id)->update($update);

            // 새글 INSERT
            g5_board_new::create(['bo_table' => 'test','wr_id' => $wr_id,'wr_parent' => $wr_id,'bn_datetime' => $now,'mb_id' => 'admin','as_reply' => '','as_re_mb' => '','as_update'=>$now]);

            // 게시글 1 증가
            $find = g5_board::where('bo_table','=','test')->get();
            $bo_count_write = (int) $find[0]['bo_count_write']+1;
            g5_board::where('bo_table','=','test')->update(['bo_count_write' => $bo_count_write]);
        }
    }

    function proper_parse_str($str) {
        $arr = array();

        $pairs = explode('&', $str);

        foreach ($pairs as $i) {
            list($name,$value) = explode('=', $i, 2);

            if( isset($arr[$name]) ) {
                if( is_array($arr[$name]) ) {
                    $arr[$name][] = $value;
                }
                else {
                    $arr[$name] = array($arr[$name], $value);
                }
            }
            else {
                $arr[$name] = $value;
            }
        }

        return $arr;
    }

    function get_next_num()
    {
        $wr_num = g5_write_humor::min('wr_num');

        return (int)$wr_num - 1;
    }

    function overlap_check($title)
    {
        $count = g5_write_humor::where('wr_subject', '=', $title)->count();
        $result = true;

        if ($count > 1) {
          $result = false;
        }

        return $result;
    }

}
