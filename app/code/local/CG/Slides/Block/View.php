<?php
class CG_Slides_Block_View
	extends Mage_Core_Block_Abstract
{
	protected function _toHtml()
	{
		$active = Mage::getStoreConfig('slides/homepage/active');
		$interval = Mage::getStoreConfig('slides/homepage/home_page_slide_interval');
		$pause_interval = Mage::getStoreConfig('slides/homepage/home_page_slide_pause_interval');
		$hover_pause = Mage::getStoreConfig('slides/homepage/home_page_hover_pause');
		$text = ($hover_pause)?'true':'false';
		if($active){
			return '
			<script type="text/javascript">
			//<![CDATA[
				jQuery("#slides").slides({
				 	play: '.$interval.',
					pause: '.$pause_interval.',
					hoverPause: '.$text.'
				});
			//]]>
			</script>
			';
		}
	}
}