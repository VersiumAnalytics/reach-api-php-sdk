<?php
use PHPUnit\Framework\TestCase;
use VersiumReach\ReachClient;
class testVersiumREACH extends TestCase {
	public function testAppendWithEmptyInput() {
		$VersiumREACH = new ReachClient('');
		$this->assertEquals([], $VersiumREACH->append('', []));
	}
}