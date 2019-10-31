<?php
	
namespace Drupal\payment_asp;

/**
 *
 *
 */

class languageCheck {
	function isKanji($str) {
	    return preg_match('/[\x{4E00}-\x{9FBF}]/u', $str) > 0;
	}

	function isHiragana($str) {
	    return preg_match('/[\x{3040}-\x{309F}]/u', $str) > 0;
	}

	function isKatakana($str) {
	    return preg_match('/[\x{30A0}-\x{30FF}]/u', $str) > 0;
	}

	function isJapanese($str) {
	    return $this->isKanji($str) || $this->isHiragana($str) || $this->isKatakana($str);
	}
}