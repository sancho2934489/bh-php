<?php
/**
 * Created by PhpStorm.
 * User: sancho
 * Date: 20.07.17
 * Time: 10:19
 */

require_once "../index.php";

class BemHtmlConverter {
    const BLOCKS = 'desktop.blocks';
    const FILE_EXT = '.bemhtml.js';

    public $filename = '';

    private $bemPath = '';
    private $template = '';
    private $textFile = '';
    public $json = '';
    private $varsName = '';
    private $varName = '';

    public $bh;

    public $block = '';
    public $element = '';
    public $blockElement = '';
    public $params = array();

    function __construct($template/*,$bemPath,$projectPath=null*/)
    {
        $this->bemPath = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'BEM';
        $this->template = $template;
        $this->bh = new \BEM\BH();
    }

    private function getTextFile() {
        $file = $this->bemPath . DIRECTORY_SEPARATOR . self::BLOCKS . DIRECTORY_SEPARATOR . $this->template . DIRECTORY_SEPARATOR . $this->template . self::FILE_EXT;
        if ($textFile = file_get_contents($file)) {
            $textFile = trim($textFile);
            var_dump($textFile);
            $textFile = preg_replace('/[\s\n\t]/','',$textFile);
            $this->textFile = $textFile;
        } else {
            die('Не найден шаблон ' . $this->template);
        }
    }

    public function getElements() {
        $this->getTextFile();
        $blockElementString = '';
        if (preg_match('/block\([\'\"](.*?)[\'\"]\)/',$this->textFile, $block) && !preg_match('/block\([\'\"](.*?)[\'\"]\)\.elem\([\'\"](.*?)[\'\"]\)/',$this->textFile)) {
            $blockElementString = $block[0];
            $block = $block[1];
            $this->blockElement = $block;
        } elseif (preg_match('/block\([\'\"].*[\'\"]\)\.elem\([\'\"].*[\'\"]\)/',$this->textFile,$textString)) {
            $blockElementString = $textString[0];
            preg_match('/block\([\'\"](.*?)[\'\"]\)/',$textString[0], $block);
            $block = $block[1];
            preg_match('/elem\([\'\"](.*?)[\'\"]\)/',$textString[0], $elem);
            $elem = $elem[1];
            $this->blockElement = $block . '__' . $elem;
            $this->block = $block;
            $this->element = $elem;
        }
        $this->textFile = str_replace($blockElementString,'',$this->textFile);
    }

    public function getContent() {
        var_dump($this->textFile);
        if (preg_match('/content\(\)\((.*?)\)[\,\)]/i',$this->textFile,$textString)) {
            var_dump($textString);
            $contentFunction = preg_replace('/^function\(\)\{return|\;\}$/','',$textString[1]);
            var_dump($contentFunction);
            $this->textFile = str_replace($textString[0],'',$this->textFile);
            var_dump($this->textFile);
            if (preg_match('/this\.ctx\.(.*?)\.map/i',$contentFunction,$textString)) {
                var_dump($textString);
                $this->varsName = $textString[1];
                $contentFunction = preg_replace('/^'.$textString[0].'\(|\)$/','',$contentFunction);
                var_dump($contentFunction);
                print $contentFunction;
                if (preg_match('/function\((.*?)\)\{return[\[\{](.*?)[,\]\}]\;\}/i',$contentFunction,$textString)) {
                    var_dump($textString);
                    $this->varName = $textString[1];
                    $this->json = $textString[2];
                    if (!preg_match('/^\{|(\}\,|\})$/i',$this->json)) $this->json = '{'.$this->json.'}';
                    var_dump($this->json);
                }
            }
        }
    }

    public function setParams() {
        $items = '';
        var_dump($this->params);
        foreach ($this->params as $param) {
            $item = $this->json;
            var_dump($item);
            preg_match_all('/item\.([a-zA-Z\-0-9\_]*)/',$item,$varsitem);
            foreach ($varsitem[1] as $varitem) {
                if (!isset($param[$varitem])) $param[$varitem] = '';
            }
            foreach ($param as $key => $val) {
                if (preg_match('/'. $this->varName . '\.' . $key .'[\&\|]{2}\'(.{2,3})\'/',$item,$z)) {
                    $replacement = (strlen($val) == 0)?"'".$z[1]."'":"'$val'";
                    $item = preg_replace('/'. $this->varName . '\.' . $key .'[\&\|]{2}\'(.{2,3})\'/',$replacement,$item);
                }
                if (preg_match('/'. $this->varName . '\.' . $key .'\?(.*?)\:\'\'(\)|\])/',$item,$z)) {
                    $replacement = (strlen($val) != 0)?$z[1]:''.$z[2];
                    $item = preg_replace('/'. $this->varName . '\.' . $key .'\?(.*?)\:\'\'(\)|\])/',$replacement,$item);
                }
                var_dump($this->varName . '.' . $key,"'$val'");
                $item = str_replace($this->varName . '.' . $key,"'$val'",$item);
                var_dump($item);
            }
            $items .= $item.',';
            var_dump($items);
        }
        var_dump('['.$items.']');
        $this->bh->match($this->blockElement,function ($ctx) use ($items) {
            $ctx->content($this->bh->processBemjson('['.$items.']'));
        });///////////////////////////////

    }

    public function updateBlock() {
        $this->textFile = preg_replace('/^\(|\)$/','',$this->textFile);
        foreach (preg_split('/,(?=(elem|block))/',$this->textFile) as $string) {
            if (!preg_match('/block/',$string)) continue;
            $string = str_replace(';','',$string);
            preg_match("/block\(\'(.*?)\'\)\((.*?)\)$/",$string,$stringMatches);
            $block = $stringMatches[1];
            preg_match_all("/([a-zA-Z\-]*?)\(\)\((\'.*?\')\)/i",$string,$z);
            for ($i=0;$i<count($z[1]);$i++) {
                $action = $z[1][$i];
                $param = $z[2][$i];
                $command = "\$ctx->".$action."(".$param.");";
                $this->bh->match($block,function ($ctx) use ($command) {
                    eval($command);
                });
            }
            var_dump($string);
            preg_match_all("/([a-zA-Z\-]*?)\(\)\(function\(\)\{return(.*?)}\)/i",$string,$z);
            for ($i=0;$i<count($z[1]);$i++) {
                $action = $z[1][$i];
                $response = $z[2][$i];
                var_dump($response);
                if (preg_match('/^\[(.*?)\]$/',$response,$responseMatchers)) {
                    $params = $responseMatchers[1];
                    $params = preg_split('/,/',$params);
                    var_dump($params);
                    foreach ($params as $param) {
                        preg_match('/^\{(.*?)\:(.*?)\}$/i',$param,$paramMatchers);
                        $key = $paramMatchers[1];
                        $val = $paramMatchers[2];
                        if (preg_match('/^this\.ctx\.(.*?)$/i',$val,$valMatchers)) $val = $valMatchers[1];
                        $command = "\$ctx->".$action."(array('".$key."'=>\$ctx->ctx->".$val."));";
                        var_dump($command);
                        $this->bh->match($block,function ($ctx) use ($command) {
                            eval($command);
                        });
                    }
                } elseif (preg_match('/^\{(.*?)\}$/i',$response,$responseMatchers)) {
                    $param = $responseMatchers[1];
                    var_dump($param);
                    preg_match('/^(.*?)\:(.*?)$/i',$param,$paramMatchers);
                    var_dump($paramMatchers);
                    $key = $paramMatchers[1];
                    var_dump($key);
                    $val = $paramMatchers[2];
                    if (preg_match('/^this\.ctx\.(.*?)$/i',$val,$valMatchers)) $val = $valMatchers[1];
                    $command = "\$ctx->".$action."(array('".$key."'=>\$ctx->ctx->".$val."));";
                    var_dump($command);
                    $this->bh->match($block,function ($ctx) use ($command) {
                        eval($command);
                    });
                }
            }
        }
    }

    public function updateElement() {
        $this->textFile = preg_replace('/^\(|\)$/','',$this->textFile);
        foreach (preg_split('/,(?=(elem|block))/',$this->textFile) as $string) {
            if (!preg_match('/elem/',$string)) continue;
            $string = str_replace(';','',$string);
            preg_match("/elem\(\'(.*?)\'\)\((.*?)\)$/",$string,$stringMatches);
            $elem = $this->block . '__' . $stringMatches[1];
            preg_match_all("/([a-zA-Z\-]*?)\(\)\((\'.*?\')\)/i",$string,$z);
            var_dump($z);
            for ($i=0;$i<count($z[1]);$i++) {
                $action = $z[1][$i];
                $param = $z[2][$i];
                $command = "\$ctx->".$action."(".$param.");";
                var_dump($command);
                $this->bh->match($elem,function ($ctx) use ($command) {
                    eval($command);
                });
            }
            preg_match_all("/([a-zA-Z\-]*?)\(\)\(function\(\)\{return(.*?)}\)/i",$string,$z);
            for ($i=0;$i<count($z[1]);$i++) {
                $action = $z[1][$i];
                $response = $z[2][$i];

                if (preg_match('/^\[(.*?)\]$/',$response,$responseMatchers)) {
                    $params = $responseMatchers[1];
                    $params = preg_split('/,/',$params);
                    foreach ($params as $param) {
                        preg_match('/^\{(.*?)\:(.*?)\}$/i',$param,$paramMatchers);
                        var_dump($paramMatchers);
                        $key = $paramMatchers[1];
                        $val = $paramMatchers[2];
                        if (preg_match('/^this\.ctx\.(.*?)$/i',$val,$valMatchers)) {
                            $val = $valMatchers[1];
                            $command = "\$ctx->".$action."(array('".$key."'=>\$ctx->ctx->".$val."));";
                            var_dump($command);
                            $this->bh->match($elem,function ($ctx) use ($command) {
                                eval($command);
                            });
                        } else {
                            $command = "\$ctx->" . $action . "(array('" . $key . "'=>" . $val . "));";
                            var_dump($command);
                            var_dump($elem);
                            $this->bh->match($elem,function ($ctx) use ($command) {
                                eval($command);
                            });
                        }
                    }
                } elseif (preg_match('/^\{(.*?)\}$/i',$response,$responseMatchers)) {
                    $param = $responseMatchers[1];
                    var_dump($param);
                    preg_match('/^(.*?)\:(.*?)$/i',$param,$paramMatchers);
                    var_dump($paramMatchers);
                    $key = $paramMatchers[1];
                    var_dump($key);
                    $val = $paramMatchers[2];
                    if (preg_match('/^this\.ctx\.(.*?)$/i',$val,$valMatchers)) $val = $valMatchers[1];
                    $command = "\$ctx->".$action."(array('".$key."'=>\$ctx->ctx->".$val."));";
                    var_dump($command);
                    $this->bh->match($elem,function ($ctx) use ($command) {
                        eval($command);
                    });
                }
            }
        }
    }
}
$start = microtime(true);

$z = new BemHtmlConverter('post');
$z->params = array(
            array(
                'title' => 'Больше не нужно заказывать пиццу домой Больше не нужно заказывать пиццу домой',
                'text' => 'Вкусный, ароматный напиток с клюквой и Иван-чаем укрепит иммунитет и согреет в зимние холода Вкусный, ароматный напиток с клюквой и Иван-чаем укрепит иммунитет и согреет в зимние холода',
                'label' => 'Кухни мира',
                'image' => '/img/post-1.png',
                'url' => '/'
            ),
    array(
        'title' => 'Больше не нужно заказывать пиццу домой Больше не нужно заказывать пиццу домой',
        'text' => 'Вкусный, ароматный напиток с клюквой и Иван-чаем укрепит иммунитет и согреет в зимние холода Вкусный, ароматный напиток с клюквой и Иван-чаем укрепит иммунитет и согреет в зимние холода',
        'label' => 'Кухни мира',
        'image' => '/img/post-1.png',
        'url' => '/'
    ),
    array(
        'title' => 'Больше не нужно заказывать пиццу домой Больше не нужно заказывать пиццу домой',
        'text' => 'Вкусный, ароматный напиток с клюквой и Иван-чаем укрепит иммунитет и согреет в зимние холода Вкусный, ароматный напиток с клюквой и Иван-чаем укрепит иммунитет и согреет в зимние холода',
        'label' => 'Кухни мира',
        'image' => '/img/post-1.png',
        'url' => '/'
    ),
    array(
        'title' => 'Больше не нужно заказывать пиццу домой Больше не нужно заказывать пиццу домой',
        'text' => 'Вкусный, ароматный напиток с клюквой и Иван-чаем укрепит иммунитет и согреет в зимние холода Вкусный, ароматный напиток с клюквой и Иван-чаем укрепит иммунитет и согреет в зимние холода',
        'label' => 'Кухни мира',
        'image' => '/img/post-1.png',
        'url' => '/'
    ),
    array(
        'title' => 'Больше не нужно заказывать пиццу домой Больше не нужно заказывать пиццу домой',
        'text' => 'Вкусный, ароматный напиток с клюквой и Иван-чаем укрепит иммунитет и согреет в зимние холода Вкусный, ароматный напиток с клюквой и Иван-чаем укрепит иммунитет и согреет в зимние холода',
        'label' => 'Кухни мира',
        'image' => '/img/post-1.png',
        'url' => '/'
    ),
);
$z->getElements();
$z->getContent();

//var_dump($z);

$z->setParams();
$z->updateBlock();
$z->updateElement();

print $z->bh->apply("{
    block: 'container',
    content: [
        {
            block: 'post',
            goods: [
                {
                    title: 'Больше не нужно заказывать пиццу домой Больше не нужно заказывать пиццу домой',
                    text: 'Вкусный, ароматный напиток с клюквой и Иван-чаем укрепит иммунитет и согреет в зимние холода Вкусный, ароматный напиток с клюквой и Иван-чаем укрепит иммунитет и согреет в зимние холода',
                    label: 'Кухни мира',
                    image: '/img/post-1.png',
                    url: '/'
                },
                {
                    title: 'Пора дегустировать Иван-чай!',
                    text: 'Вкусный, ароматный напиток с клюквой и Иван-чаем укрепит иммунитет и согреет в зимние холода Вкусный, ароматный напиток с клюквой и Иван-чаем укрепит иммунитет и согреет в зимние холода',
                    label: 'Новости',
                    image: '/img/post-2.png',
                    url: '/'
                },
                {
                    title: 'Чайный напиток с клюквой если длиное название',
                    text: 'Вкусный, ароматный напиток с клюквой и Иван-чаем укрепит иммунитет и согреет в зимние холода Вкусный, ароматный напиток с клюквой и Иван-чаем укрепит иммунитет и согреет в зимние холода',
                    label: 'Напитки',
                    image: '/img/post-3.png',
                    autorName: 'Анна Паршинцева', 
                    avatar: '/img/avatar-1.png', 
                    autorRating: 'Эксперт (239779)', 
                    star:'',
                    url: '/'
                },
                {
                    title: 'Утка с айвой',
                    text: 'Вкусный, ароматный напиток с клюквой и Иван-чаем укрепит иммунитет и согреет в зимние холода Вкусный, ароматный напиток с клюквой и Иван-чаем укрепит иммунитет и согреет в зимние холода',
                    label: 'Вторые блюда',
                    image: '/img/post-4.png',
                    autorName: 'Ольга Малышева', 
                    avatar: '/img/avatar-2.png', 
                    autorRating: 'Эксперт (239779)',
                    youtube_icon: true, 
                    star:'',
                    url: '/'
                }        
            ]
        },
        {
            block: 'button',
            mods: { theme: 'default', size: 'm' },
            mix: {block: 'post', elem: 'postYet'},
            text: 'Показать еще'
        }
    ]
}");

$stop = microtime(true);

var_dump($stop-$start);