<?php
use PHPUnit\Framework\TestCase;
use VersiumREACH\VersiumREACH;
class testVersiumREACH extends TestCase {
	public function testAppendWithEmptyInput() {
		$VersiumREACH = new VersiumREACH('');
		$this->assertEquals([], $VersiumREACH->append('', []));
	}
}