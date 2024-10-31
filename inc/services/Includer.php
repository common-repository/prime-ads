<?php

namespace AdsNS\Services;

class Includer{

	private $allowedTags = array('p', 'div', 'blockquote', 'ul', 'ol', 'h2', 'h3', 'h4', 'h5', 'h6');
	private $parTags = array('p', 'blockquote', 'ol', 'ul');
	private $listTags = array('ol', 'ul');
	private $headerTags = array('h2', 'h3', 'h4', 'h5', 'h6');
	private $disallowClasses = array('prma-no', 'recomend-section', 'yarpp-related', 'article-post', 'article-content', 'wp-caption'); // не заходить в div с этими классами
	private $disallowIds = array('toc_container');

	private $positions;
	private $content;

	private $charsCounter = 0;
	private $parsCounter = 0;
	private $lastPar = false;
	private $pars = array();

	private $headersCounter = 0;

	/**
	 * Запускает процесс интеграции блоков в контент
	 */
	public function doInclude(&$positions, $content)
	{
		$this->positions = $positions;
		$this->content = $content;

		if(count($positions) == 0) return $content;

		return $this->_doContent();
	}

	/**
	 * Возвращает контент с блоками
	 */
	private function _doContent()
	{
    	$doc = new \DOMDocument();
    	libxml_use_internal_errors(true);
    	@$doc->loadHTML("<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />" . $this->content);
    	$nodeList = $doc->getElementsByTagName('body')->item(0)->childNodes;

    	foreach ($nodeList as $node) {
    	    $this->_parseNode($node,$doc);
    	}

    	// если есть позиции по картинкам
    	if(isset($this->positions[AdsHelper::POSITION_AFTER_IMAGE]) || 
    		isset($this->positions[AdsHelper::POSITION_BEFORE_IMAGE]) ||
    		isset($this->positions[AdsHelper::POSITION_IMAGE_FLIP]) ||
    		isset($this->positions[AdsHelper::POSITION_ALL_IMAGE_FLIP])){
    		$this->_handleImageNodes($doc);
    	}

    	// если есть позиции с конца контента
    	if(isset($this->positions[AdsHelper::POSITION_BEFORE_PAR_FROM_END])){
    		$this->_workParsFromEnd($doc);
    	}

	    $content = $doc->saveHTML();

        // вставляем в начало
        if(isset($this->positions[AdsHelper::POSITION_UNDER_HEADER])){
        	$this->positions[AdsHelper::POSITION_UNDER_HEADER] = array_reverse($this->positions[AdsHelper::POSITION_UNDER_HEADER]);
        	$underHeader = '';
        	foreach ($this->positions[AdsHelper::POSITION_UNDER_HEADER] as $key => $value)
    			$underHeader .= '<!--position_' . AdsHelper::POSITION_UNDER_HEADER . '-' . $key . '-->';//$value['position_data'];
        	$content = $underHeader . $content;
        }

        // вставляем в конец
        if(isset($this->positions[AdsHelper::POSITION_UNDER_POST])){
        	$this->positions[AdsHelper::POSITION_UNDER_POST] = array_reverse($this->positions[AdsHelper::POSITION_UNDER_POST]);
        	$underPost = '';
        	foreach ($this->positions[AdsHelper::POSITION_UNDER_POST] as $key => $value) 
        		$underPost .= '<!--position_' . AdsHelper::POSITION_UNDER_POST . '-' . $key . '-->';//$value['position_data'];
        	$content = $content . $underPost;
        }

	    // заменяем вставки
	    $content = $this->_insertPositions($content);

	    $replace = array(
	        '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN" "http://www.w3.org/TR/REC-html40/loose.dtd">' => '',
	        '<html>' => '',
	        '</html>' => '',
	        '<head>' => '',
	        '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">' => '',
	        '</head>' => '',
	        '<body>' => '',
	        '</body>' => '',
	        '<br class="hovered_play_icon">'=>'<img class="hovered_play_icon" src="'.$url_img_adr_play.'">'
	    );
	    $content = str_replace(array_keys($replace), array_values($replace), $content);
	    return do_shortcode($content);
	}

	/**
	 * Обходит дерево контента и вставляет метки на нужные места
	 */
	private function _parseNode($node, $doc)
	{
		$nodeTag = (isset($node->tagName)) ? $node->tagName : '';
		if (!in_array($nodeTag, $this->allowedTags) || trim($node->nodeValue) == '')
		    return;

		$element = false;

		// если узел - эелемент абзаца
		if (in_array($nodeTag, $this->parTags)) {
		    $this->parsCounter++;
		    $this->pars[$this->parsCounter] = $node;
		    $this->lastPar = $node;
		    $parLength = mb_strlen($node->nodeValue, 'UTF-8');
		    $this->charsCounter += $parLength;
		    if(isset($this->positions[AdsHelper::POSITION_AFTER_PAR])){
		    	foreach ($this->positions[AdsHelper::POSITION_AFTER_PAR] as $key => $value) {
		    		if($value['count'] == $this->parsCounter){
		    			$element = $doc->createComment('position_' . AdsHelper::POSITION_AFTER_PAR . '-' . $key);
		    			$node->parentNode->insertBefore($element, $node->nextSibling);
		    		}
		    		if($this->parsCounter == 1 && $value['count'] == 0){
		    			$element = $doc->createComment('position_' . AdsHelper::POSITION_AFTER_PAR . '-' . $key);
		    			$node->parentNode->insertBefore($element, $node);
		    		}
		    	}
		    }
		    
		    if(isset($this->positions[AdsHelper::POSITION_AFTER_CHARS_NUM])){
		    	$keys = array();
		    	foreach ($this->positions[AdsHelper::POSITION_AFTER_CHARS_NUM] as $key => $value) {
		    		if($value['count'] > $this->charsCounter - $parLength && $value['count'] <= $this->charsCounter){
		    			$keys[$value['count']] = $key;
		    		}
		    	}
		    	if($keys){
		    		ksort($keys);
		    		$key = reset($keys);
		    		$element = $doc->createComment('position_' . AdsHelper::POSITION_AFTER_CHARS_NUM . '-' . $key);
		    		$node->parentNode->insertBefore($element, $node->nextSibling);
		    	}
		    }
		// если узел - подзаголовок
		}else if(in_array($nodeTag, $this->headerTags)){
			$this->headersCounter++;
			if(isset($this->positions[AdsHelper::POSITION_BEFORE_SUBHEADER])){
				foreach ($this->positions[AdsHelper::POSITION_BEFORE_SUBHEADER] as $key => $value) {
					if($value['count'] == $this->headersCounter){
						$element = $doc->createComment('position_' . AdsHelper::POSITION_BEFORE_SUBHEADER . '-' . $key);
						$node->parentNode->insertBefore($element, $node);
					}
				}
			}
			if(isset($this->positions[AdsHelper::POSITION_AFTER_SUBHEADER])){
				foreach ($this->positions[AdsHelper::POSITION_AFTER_SUBHEADER] as $key => $value) {
					if($value['count'] == $this->headersCounter){
						$element = $doc->createComment('position_' . AdsHelper::POSITION_AFTER_SUBHEADER . '-' . $key);
						$node->parentNode->insertBefore($element, $node->nextSibling);
					}
				}
			}
		}

		// После/перед содержания TOC+
		if($nodeTag == 'div' && ($node->getAttribute('id') == 'toc_container' || $node->getAttribute('class') == 'article-content')){
			if(isset($this->positions[AdsHelper::POSITION_AFTER_TOC])){
				foreach ($this->positions[AdsHelper::POSITION_AFTER_TOC] as $key => $value) {
					$element = $doc->createComment('position_' . AdsHelper::POSITION_AFTER_TOC . '-' . $key);
					$node->parentNode->insertBefore($element, $node->nextSibling);
				}
			}
			if(isset($this->positions[AdsHelper::POSITION_BEFORE_TOC])){
				foreach ($this->positions[AdsHelper::POSITION_BEFORE_TOC] as $key => $value) {
					$element = $doc->createComment('position_' . AdsHelper::POSITION_BEFORE_TOC . '-' . $key);
					$node->parentNode->insertBefore($element, $node);
				}
			}
			if(isset($this->positions[AdsHelper::POSITION_RIGHT_OF_TOC])){
				foreach ($this->positions[AdsHelper::POSITION_RIGHT_OF_TOC] as $key => $value) {
					$wrap = $doc->createElement('div');
					$wrap->setAttribute('class', 'toc-wrap');
					$place = $doc->createElement('div');
					$element = $doc->createComment('position_' . AdsHelper::POSITION_RIGHT_OF_TOC . '-' . $key);
					$place->appendChild($element);
					$sibling = $node->nextSibling;
					$node->parentNode->removeChild($node);
					$wrap->appendChild($node);
					$wrap->appendChild($place);
					$sibling->parentNode->insertBefore($wrap, $sibling);
				}
			}
		}

		// После/перед содержания TexterPub
		if($nodeTag == 'div' && $node->getAttribute('class') == 'article-post'){
			if(isset($this->positions[AdsHelper::POSITION_AFTER_TEXTERPUB_CONTENT])){
				foreach ($this->positions[AdsHelper::POSITION_AFTER_TEXTERPUB_CONTENT] as $key => $value) {
					$element = $doc->createComment('position_' . AdsHelper::POSITION_AFTER_TEXTERPUB_CONTENT . '-' . $key);
					$node->parentNode->insertBefore($element, $node->nextSibling);
				}
			}
			if(isset($this->positions[AdsHelper::POSITION_BEFORE_TEXTERPUB_CONTENT])){
				foreach ($this->positions[AdsHelper::POSITION_BEFORE_TEXTERPUB_CONTENT] as $key => $value) {
					$element = $doc->createComment('position_' . AdsHelper::POSITION_BEFORE_TEXTERPUB_CONTENT . '-' . $key);
					$node->parentNode->insertBefore($element, $node);
				}
			}
		}

		// Блок ссылок "Рекомендуем"
		if($nodeTag == 'div' && $node->getAttribute('class') == 'recomend-section'){
			foreach($node->childNodes as $cn){
			    if(isset($cn->tagName) && $cn->tagName == 'ul'){
			    	if(isset($this->positions[AdsHelper::POSITION_LINK_IN_PERELINK])){
			    		foreach ($this->positions[AdsHelper::POSITION_LINK_IN_PERELINK] as $key => $value) {
			    			$element = $doc->createComment('position_' . AdsHelper::POSITION_LINK_IN_PERELINK . '-' . $key);
			    			$cn->appendChild($element);
			    		}
			    	}
			    }
			}
		}

		// если узел - блок. Идём по дочерним
		if($nodeTag == 'div' && $node->childNodes && count(array_intersect(explode(' ', $node->getAttribute('class')), $this->disallowClasses)) == 0 && !in_array($node->getAttribute('id'), $this->disallowIds)) {
			foreach ($node->childNodes as $cNode) {
				$this->_parseNode($cNode, $doc);
			}
		}
	}

	/**
	 * Обрабатывает позиции с картинками
	 */
	private function _handleImageNodes($doc)
	{
		$counter = 0;
		if (!$imageNodes = $doc->getElementsByTagName('img'))
			return;

		$domElemsToRemove = array(); 
	    foreach ($imageNodes as $node) {
	        $counter++;
	        if(isset($this->positions[AdsHelper::POSITION_AFTER_IMAGE]))
		        foreach ($this->positions[AdsHelper::POSITION_AFTER_IMAGE] as $key => $value) {
		        	if($value['count'] != $counter)	continue;
		        	$element = $doc->createComment('position_' . AdsHelper::POSITION_AFTER_IMAGE . '-' . $key);        	
		        	$targetNode = $this->_getImageTargetNode($node, $doc);
		        	$targetNode->parentNode->insertBefore($element, $targetNode->nextSibling);       	
		        } 

		    if(isset($this->positions[AdsHelper::POSITION_BEFORE_IMAGE]))
		        foreach ($this->positions[AdsHelper::POSITION_BEFORE_IMAGE] as $key => $value) {
		        	if($value['count'] != $counter)	continue;
		        	$element = $doc->createComment('position_' . AdsHelper::POSITION_BEFORE_IMAGE . '-' . $key);        	
		        	$targetNode = $this->_getImageTargetNode($node, $doc);
		        	$targetNode->parentNode->insertBefore($element, $targetNode);       	
		        }  
		    if(isset($this->positions[AdsHelper::POSITION_IMAGE_FLIP]))
		        foreach ($this->positions[AdsHelper::POSITION_IMAGE_FLIP] as $key => $value) {
		        	if($value['count'] != $counter)	continue;
		        	$element = $doc->createComment('position_' . AdsHelper::POSITION_IMAGE_FLIP . '-' . $key);        	
		        	$targetNode = $this->_getImageTargetNode($node, $doc);
		        	$targetNode->parentNode->insertBefore($element, $targetNode);
		        	$this->positions[AdsHelper::POSITION_IMAGE_FLIP][$key]['position_data'] = 
		        		AdsHelper::generateFlipImage(
		        			$doc->saveXML($targetNode), 
		        			$key, 
		        			$this->positions[AdsHelper::POSITION_IMAGE_FLIP][$key]['position_data'],
		        			$this->positions[AdsHelper::POSITION_IMAGE_FLIP][$key]['async']); 
		        	$domElemsToRemove[] = $targetNode; 
		        	break; // только одна позиция за картинкой     	
		        }   
		    if(isset($this->positions[AdsHelper::POSITION_ALL_IMAGE_FLIP])){
		    	$hasDirectBlockAfterThisImage = false;
		    	if(isset($this->positions[AdsHelper::POSITION_IMAGE_FLIP]))
		    	    foreach ($this->positions[AdsHelper::POSITION_IMAGE_FLIP] as $key => $value) {
		    	    	if($value['count'] == $counter){
		    	    		$hasDirectBlockAfterThisImage = true;
		    	    		break;
		    	    	}
		    	    }
		    	if($hasDirectBlockAfterThisImage) continue;
        		$element = $doc->createComment('position_' . AdsHelper::POSITION_ALL_IMAGE_FLIP . '-' . $counter);        	
        		$targetNode = $this->_getImageTargetNode($node, $doc);
        		$targetNode->parentNode->insertBefore($element, $targetNode);
        		$this->positions[AdsHelper::POSITION_ALL_IMAGE_FLIP][$counter]['position_data'] = 
        			AdsHelper::generateFlipImage(
        				$doc->saveXML($targetNode), 
        				$counter, 
        				$this->positions[AdsHelper::POSITION_ALL_IMAGE_FLIP][0]['position_data'],
        				$this->positions[AdsHelper::POSITION_ALL_IMAGE_FLIP][0]['async']); 
        		$this->positions[AdsHelper::POSITION_ALL_IMAGE_FLIP][$counter]['async'] = 
        			$this->positions[AdsHelper::POSITION_ALL_IMAGE_FLIP][0]['async'];
        		$this->positions[AdsHelper::POSITION_ALL_IMAGE_FLIP][$counter]['id'] = 
        			$this->positions[AdsHelper::POSITION_ALL_IMAGE_FLIP][0]['id'];
        		$domElemsToRemove[] = $targetNode; 	
	        }  
	    }
	    if(isset($this->positions[AdsHelper::POSITION_ALL_IMAGE_FLIP]))
	    	unset($this->positions[AdsHelper::POSITION_ALL_IMAGE_FLIP][0]);

	    foreach( $domElemsToRemove as $domElement ){
	      $domElement->parentNode->removeChild($domElement);
	    } 
	}
	/**
	 * Возвращает элемент картинки (картинка может быть обёрнута в <a> и/или <p>)
	 */
	private function _getImageTargetNode($node, $doc)
	{
		$firstParent = $node->parentNode;
		if($firstParent && $firstParent->tagName == 'p'){
			$targetNode = $firstParent;
		}else if($firstParent && $firstParent->tagName == 'a'){
			$secondParent = $firstParent->parentNode;
			if($secondParent && $secondParent->tagName == 'p'){
				$targetNode = $secondParent;
			}else{
				$targetNode = $firstParent;
			}
		}else if($firstParent && $firstParent->tagName == 'div' && 
			strpos($firstParent->getAttribute('class'), 'wp-caption') !== false){
			$targetNode = $firstParent;
		}else{
			$targetNode = $node;
		}
		return $targetNode;
	}

	/**
	 * Обрабатывает позиции с конца контента
	 */
	private function _workParsFromEnd($doc)
	{
		if(count($this->pars) == 0) return;
		$pars = array_reverse($this->pars);
        if(isset($this->positions[AdsHelper::POSITION_BEFORE_PAR_FROM_END]))
	        foreach ($this->positions[AdsHelper::POSITION_BEFORE_PAR_FROM_END] as $key => $value) {
	        	if(!isset($pars[$value['count']])) continue;
	        	$node = $pars[$value['count']];
	        	$element = $doc->createComment('position_' . AdsHelper::POSITION_BEFORE_PAR_FROM_END . '-' . $key);        	
	        	$parent = $node->parentNode;
	        	if($parent) 	
	        		$parent->insertBefore($element, $node->nextSibling);       	
	        }
	}

	/**
	 * Производит замену меток на блоки
	 */
	private function _insertPositions($content)
	{
		foreach ($this->positions as $place => $arr) 
			foreach ($arr as $key => $data)
				if($data['async']){
					$pointer = '<div class="prma-position-pointer" data-id="' . $data['id'] . '" data-place="' . $place . '">' . $data['position_data'] . '</div>';
					$content = str_replace('<!--position_' . $place . '-' . $key . '-->', $pointer, $content);
				}else{
					$content = str_replace('<!--position_' . $place . '-' . $key . '-->', $data['position_data'], $content);
				}
			
		return $content;
	}
}

?>
