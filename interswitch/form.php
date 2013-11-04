<?php defined('_JEXEC') or die(); ?>
<?php
$t1 = JText::_('COM_AKEEBASUBS_LEVEL_REDIRECTING_HEADER');
$t2 = JText::_('COM_AKEEBASUBS_LEVEL_REDIRECTING_BODY');
?>

<h3><?php echo JText::_('COM_AKEEBASUBS_LEVEL_REDIRECTING_HEADER') ?></h3>
<p><?php echo JText::_('COM_AKEEBASUBS_LEVEL_REDIRECTING_BODY') ?></p>
<p align="center">
<form action="<?php echo $data->payment_url ?>"  method="post" id="paymentForm">
	<input type="hidden" name="product_id" value="<?php echo $data->product_id ?>" />
	<input type="hidden" name="pay_item_id" value="<?php echo $data->pay_item_id ?>" />
	<input type="hidden" name="txn_ref" value="<?php echo $data->txn_ref ?>" />
	<input type="hidden" name="amount" value="<?php echo $data->amount ?>" />
	<input type="hidden" name="currency" value="<?php echo $data->currency ?>" />
	<input type="hidden" name="site_redirect_url" value="<?php echo $data->site_redirect_url ?>" />
	<input type="hidden" name="hash" value="<?php echo $data->hash ?>" />

	
	<?php if($cbt = $this->params->get('cbt','')): ?>
	<input type="hidden" name="cbt" value="<?php echo $cbt ?>" />
	<?php endif; ?>
	<?php if($cpp_header_image = $this->params->get('cpp_header_image','')): ?>
	<input type="hidden" name="cpp_header_image" value="<?php echo $cpp_header_image?>" />
	<?php endif; ?>
	<?php if($cpp_headerback_color = $this->params->get('cpp_headerback_color','')): ?>
	<input type="hidden" name="cpp_headerback_color" value="<?php echo $cpp_headerback_color?>" />
	<?php endif; ?>
	<?php if($cpp_headerborder_color = $this->params->get('cpp_headerborder_color','')): ?>
	<input type="hidden" name="cpp_headerborder_color" value="<?php echo $cpp_headerborder_color?>" />
	<?php endif; ?>

	<input type='submit' value='Proceed with Payment' />
	
</form>
</p>
