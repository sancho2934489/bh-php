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

    private $matchCommand = '';

    function __construct($template/*,$bemPath,$projectPath=null*/)
    {
        $this->bemPath = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'BEM';
        $this->template = $template;
        $this->bh = new \BEM\BH();
    }

    private function getFileJS($file) {
        $file = $this->bemPath . DIRECTORY_SEPARATOR . $file;
        $fileText = file_get_contents($file);
        $json = preg_replace('/^[\s\S]*module.exports =|;[\s\S]*$/', '', $fileText);
        return trim($json);
    }

    private function getTextFile() {
        $file = $this->bemPath . DIRECTORY_SEPARATOR . self::BLOCKS . DIRECTORY_SEPARATOR . $this->template . DIRECTORY_SEPARATOR . $this->template . self::FILE_EXT;
        if ($textFile = file_get_contents($file)) {
            $textFile = trim($textFile);
            while (preg_match('/include\(\'(.*?)\'\)/i',$textFile,$textFileMatches)) {
                $filePath = $textFileMatches[1];
                $json = $this->getFileJS($filePath);
                $textFile = str_replace($textFileMatches[0],$json,$textFile);
            }
            $textFile = preg_replace('/[\s\n\t]/','',$textFile);
            $this->textFile = $textFile;
        } else {
            die('Не найден шаблон ' . $this->template);
        }
    }

    private function getElements() {
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

    private function getContent() {
        $this->getElements();
        if (preg_match('/^\.match\(function\(\){return(.*?);\}\)\(/i',$this->textFile,$textFileMatches)) {
            $attr = $textFileMatches[1];
            if (preg_match('/^this\.ctx\.(.*?)$/i',$attr,$attrMatches)) {
                $attr = $attrMatches[1];
                $this->matchCommand = "\$check = \$ctx->ctx->".$attr.";";
            }
        }
        //if
        if (preg_match('/content\(\)\((.*?)\)[\,\)]/i',$this->textFile,$textString)) {
            $contentFunction = preg_replace('/^function\(\)\{return|\;\}$/','',$textString[1]);
            $this->textFile = str_replace($textString[0],'',$this->textFile);
            if (preg_match('/this\.ctx\.(.*?)\.map/i',$contentFunction,$textString)) {
                $this->varsName = $textString[1];
                $contentFunction = preg_replace('/^'.$textString[0].'\(|\)$/','',$contentFunction);
                if (preg_match('/function\((.*?)\)\{return[\[\{](.*?)[,\]\}]\;\}/i',$contentFunction,$textString)) {
                    $this->varName = $textString[1];
                    $this->json = $textString[2];
                    if (!preg_match('/^\{|(\}\,|\})$/i',$this->json)) $this->json = '{'.$this->json.'}';
                }
            }
        }
    }

    public function setParams() {
        $this->getContent();
        $items = '';
        foreach ($this->params as $param) {
            $item = $this->json;
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
                $item = str_replace($this->varName . '.' . $key,"'$val'",$item);
            }
            $items .= $item.',';
        }
        $this->bh->match($this->blockElement,function ($ctx) use ($items) {
            eval($this->matchCommand);
            if ($check || !isset($check)) $ctx->content($this->bh->processBemjson('['.$items.']'));
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
            preg_match_all("/([a-zA-Z\-]*?)\(\)\(function\(\)\{return(.*?)}\)/i",$string,$z);
            for ($i=0;$i<count($z[1]);$i++) {
                $action = $z[1][$i];
                $response = $z[2][$i];
                if (preg_match('/^\[(.*?)\]$/',$response,$responseMatchers)) {
                    $params = $responseMatchers[1];
                    $params = preg_split('/,/',$params);
                    foreach ($params as $param) {
                        preg_match('/^\{(.*?)\:(.*?)\}$/i',$param,$paramMatchers);
                        $key = $paramMatchers[1];
                        $val = $paramMatchers[2];
                        if (preg_match('/^this\.ctx\.(.*?)$/i',$val,$valMatchers)) $val = $valMatchers[1];
                        $command = "\$ctx->".$action."(array('".$key."'=>\$ctx->ctx->".$val."));";
                        $this->bh->match($block,function ($ctx) use ($command) {
                            eval($command);
                        });
                    }
                } elseif (preg_match('/^\{(.*?)\}$/i',$response,$responseMatchers)) {
                    $param = $responseMatchers[1];
                    preg_match('/^(.*?)\:(.*?)$/i',$param,$paramMatchers);
                    $key = $paramMatchers[1];
                    $val = $paramMatchers[2];
                    if (preg_match('/^this\.ctx\.(.*?)$/i',$val,$valMatchers)) $val = $valMatchers[1];
                    $command = "\$ctx->".$action."(array('".$key."'=>\$ctx->ctx->".$val."));";
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
            for ($i=0;$i<count($z[1]);$i++) {
                $action = $z[1][$i];
                $param = $z[2][$i];
                $command = "\$ctx->".$action."(".$param.");";
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
                        $key = $paramMatchers[1];
                        $val = $paramMatchers[2];
                        if (preg_match('/^this\.ctx\.(.*?)$/i',$val,$valMatchers)) {
                            $val = $valMatchers[1];
                            $command = "\$ctx->".$action."(array('".$key."'=>\$ctx->ctx->".$val."));";
                            $this->bh->match($elem,function ($ctx) use ($command) {
                                eval($command);
                            });
                        } else {
                            $command = "\$ctx->" . $action . "(array('" . $key . "'=>" . $val . "));";
                            $this->bh->match($elem,function ($ctx) use ($command) {
                                eval($command);
                            });
                        }
                    }
                } elseif (preg_match('/^\{(.*?)\}$/i',$response,$responseMatchers)) {
                    $param = $responseMatchers[1];
                    preg_match('/^(.*?)\:(.*?)$/i',$param,$paramMatchers);
                    $key = $paramMatchers[1];
                    $val = $paramMatchers[2];
                    if (preg_match('/^this\.ctx\.(.*?)$/i',$val,$valMatchers)) $val = $valMatchers[1];
                    $command = "\$ctx->".$action."(array('".$key."'=>\$ctx->ctx->".$val."));";
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
        'title' => 'title',
        'text' => 'text',
        'label' => 'label',
        'image' => 'image',
        'url' => 'url'
    ),
    array(
        'title' => 'title',
        'text' => 'text',
        'label' => 'label',
        'image' => 'image',
        'url' => 'url'
    ),
    array(
        'title' => 'title',
        'text' => 'text',
        'label' => 'label',
        'image' => 'image',
        'url' => 'url'
    ),
);

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