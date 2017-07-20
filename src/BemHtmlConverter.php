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

    private $bh;

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
        print $this->textFile.'<br><br>';
        if (preg_match('/content\(\)\((.*?)\)[\,]/',$this->textFile,$textString)) {
            var_dump($textString);
            $contentFunction = preg_replace('/^function\(\)\{return|\;\}$/','',$textString[1]);
            $this->textFile = str_replace($textString[0],'',$this->textFile);
            print $this->textFile.'<br><br>';
            print $contentFunction;
            if (preg_match('/this\.ctx\.(.*?)\.map/',$contentFunction,$textString)) {
                var_dump($textString);
                $this->varsName = $textString[1];
                $contentFunction = preg_replace('/^'.$textString[0].'\(|\)$/','',$contentFunction);
                print $contentFunction;
                if (preg_match('/function\((.*?)\)\{return\[(.*?)[,]\]\;\}/',$contentFunction,$textString)) {
                    var_dump($textString);
                    $this->varName = $textString[1];
                    $this->json = $textString[2];
                }
            }
        }
    }

    public function setParams() {
        $items = '';
        foreach ($this->params as $param) {
            $item = $this->json;
            print "!!!".$item.'<br><br>';
            preg_match_all('/item\.([a-zA-Z\-0-9\_]*)/',$item,$varsitem);
            foreach ($varsitem[1] as $varitem) {
                if (!isset($param[$varitem])) $param[$varitem] = '';
            }
            var_dump($param);
            foreach ($param as $key => $val) {
                if (preg_match('/'. $this->varName . '\.' . $key .'[\&\|]{2}\'(.{2,3})\'/',$item,$z)) {
                    $replacement = (strlen($val) == 0)?"'".$z[1]."'":"'$val'";
                    print '/'. $this->varName . '\.' . $key .'[\&\|]{2}\'(.{2,3})\'/<br><br>';
                    $item = preg_replace('/'. $this->varName . '\.' . $key .'[\&\|]{2}\'(.{2,3})\'/',$replacement,$item);
                }
                if (preg_match('/'. $this->varName . '\.' . $key .'\?(.*?)\:\'\'(\)|\])/',$item,$z)) {
                    var_dump($z);
                    $replacement = (strlen($val) != 0)?$z[1]:''.$z[2];
                    $item = preg_replace('/'. $this->varName . '\.' . $key .'\?(.*?)\:\'\'(\)|\])/',$replacement,$item);
                }
                $item = str_replace($this->varName . '.' . $key,"'$val'",$item);
            }
            print $item.'<br><br>';
            $items .= $item.',';
        }

        $this->bh->match('post',function ($ctx) use ($items) {
            $ctx->content($this->bh->processBemjson('['.$items.']'));
        });///////////////////////////////

    }

    public function updateBlock() {
        preg_match_all("/block\(\'(.*?)\'\)\((.*?)\),elem|block\(\'(.*?)\'\)\((.*?)\),block|block\(\'(.*?)\'\)\((.*\)?)\)\)/",$this->textFile,$z);
        if (count($z) > 0) {
            for ($i = 0; $i < count($z[1]); $i++) {
                $block = $z[1][$i];
                $actionBlock = $z[2][$i];

                $actions = preg_split('/,/',$actionBlock);
                foreach ($actions as $action) {
                    preg_match('/^(.*?)\(\)\((.*?)\)$/',$action,$z);
                    $key = $z[1];
                    $val = $z[2];
                    if (preg_match('/^function\(\)\{return[\[](.*?)[\]]}$|^function\(\)\{return(.*?)}$/',$val,$valMatches)) {
                        $valArray = preg_split('/,/',$valMatches[2]);
                        if (count($valArray) > 0) {
                            foreach ($valArray as $vall) {
                                preg_match('/^{(.*?)\:(.*?)};$/',$vall,$vallMatches);
                                $attribute = $vallMatches[1];
                                $attributeVall = $vallMatches[2];
                                if (preg_match('/^this\.ctx\.(.*?)$/',$attributeVall,$attributeVallMatches)) $attributeVall = $attributeVallMatches[1];
                                $command = "\$ctx->".$key."(array('".$attribute."'=>\$ctx->ctx->".$attributeVall."));";
                                $this->bh->match($block,function ($ctx) use ($command) {
                                    eval($command);
                                });
                            }
                        }
                    } else {
                        $command = "\$ctx->".$key."(".$val.");";
                        $this->bh->match($block,function ($ctx) use ($command) {
                            eval($command);
                        });
                    }
                }
            }
        }
    }

    public function updateElement() {
        $this->textFile = preg_replace('/^\(|\)$/','',$this->textFile);
        foreach (preg_split('/,(?=(elem|block))/',$this->textFile) as $string) {
            if (!preg_match('/elem/',$string)) continue;
            $string = str_replace(';','',$string);
            var_dump($string);
            preg_match("/elem\(\'(.*?)\'\)\((.*?)\)$/",$string,$stringMatches);
            var_dump($stringMatches);
            $elem = $this->block . '__' . $stringMatches[1];
            preg_match_all("/([a-zA-Z\-]*?)\(\)\(\'(.*?)\'\)/i",$string,$z);
            var_dump($z);
            //$command = "\$ctx->".$key."(".$val.");";
            for ($i=0;$i<count($z[1]);$i++) {
                $action
            }
            $this->bh->match($block,function ($ctx) use ($command) {
                eval($command);
            });
        }
    }
}

$z = new BemHtmlConverter('post');
$z->params = array(
    array(
        'title' => 'Титле',
        'text' => 'Текст',
        'label' => 'Лабел',
        'image' => 'Имаге',
        'url' => 'Урл'
    ),
    array(
        'title' => 'Титле 2',
        'text' => 'Текст 2',
        'label' => 'Лабел 2',
        'image' => 'Имаге 2',
        'url' => 'Урл 2',
        'autorName' => 'АуторНаме 2'
    ),
);
$z->getElements();
$z->getContent();

var_dump($z);

$z->setParams();
$z->updateBlock();
$z->updateElement();