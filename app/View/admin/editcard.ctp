<div class="page-header">
    <h1><?php echo $page_title ?></h1>
</div>
<?php echo $this->Form->create('Card', array('class' => 'form-horizontal', 'method' => 'post', 'enctype' => 'multipart/form-data')); ?>
<div class="form-group">
    <label for="name" class="col-sm-2 control-label">Name</label>
    <div class="col-sm-10">
        <?php echo $this->Form->input('title', array('class' => 'form-control', 'label' => false, 'value' => $cardData['Card']['title'], 'required' => 'required')); ?>
        <div class="help-block with-errors"></div>
    </div>
</div>
<div class="form-group">
    <label for="name" class="col-sm-2 control-label">Category</label>
    <div class="col-sm-10">
        <?php echo $this->Form->input('category', array('class' => 'form-control', 'label' => false, 'required' => 'required')); ?>
        <div class="help-block with-errors"></div>
    </div>
</div>
<div class="form-group">
    <label for="name" class="col-sm-2 control-label">Description</label>
    <div class="col-sm-10">
        <?php echo $this->Form->textarea('description', array('class' => 'form-control', 'label' => false, 'value' => $cardData['Card']['description'], 'required' => 'required')); ?>
        <div class="help-block with-errors"></div>
    </div>
</div>
<div class="form-group">
    <label for="name" class="col-sm-2 control-label">Image</label>
    <div class="col-sm-10">
        <?php echo $this->Form->file('image', array('class' => '', 'label' => false)); ?>
        <?php echo $this->Form->hidden('hidimage', array('class' => '', 'label' => false, 'value' => $cardData['Card']['image'])); ?>
        <?php
        $newImgPath = $cardData['Card']['image'];
        if ($cardData['Card']['image'] != '') {
            $availableServers = Configure::read('AVAILABLE_SERVERS');
            foreach ($availableServers as $serverVal) {
                if (substr_count($cardData['Card']['image'], $serverVal) > 0) {
                    $newImgPath = str_replace($serverVal, Configure::read('CURRENT_SERVER'), $cardData['Card']['image']);
                    break;
                }
            }
            ?>
            <br><img src="<?php echo $newImgPath; ?>" width="100">
        <?php } ?>
    </div>
</div>
<div class="form-group">
    <label for="name" class="col-sm-2 control-label">Image (1080)</label>
    <div class="col-sm-10">
        <?php echo $this->Form->file('image1080', array('class' => '', 'label' => false)); ?>
        <?php echo $this->Form->hidden('hidimage1080', array('class' => '', 'label' => false, 'value' => $cardData['Card']['image'])); ?>
        <?php
        $newImgPath = str_replace('cards/', 'cards/a/1080/', $cardData['Card']['image']);
        if ($cardData['Card']['image'] != '') {
            $availableServers = Configure::read('AVAILABLE_SERVERS');
            foreach ($availableServers as $serverVal) {
                if (substr_count($cardData['Card']['image'], $serverVal) > 0) {
                    $newImgPath = str_replace($serverVal, Configure::read('CURRENT_SERVER'), $cardData['Card']['image']);
                    break;
                }
            }
            ?>
            <br><img src="<?php echo $newImgPath; ?>" width="100">
        <?php } ?>
    </div>
</div>
<div class="form-group">
    <label for="name" class="col-sm-2 control-label">Image (720)</label>
    <div class="col-sm-10">
        <?php echo $this->Form->file('image720', array('class' => '', 'label' => false)); ?>
        <?php echo $this->Form->hidden('hidimage720', array('class' => '', 'label' => false, 'value' => $cardData['Card']['image'])); ?>
        <?php
        $newImgPath = str_replace('cards/', 'cards/a/720/', $cardData['Card']['image']);
        if ($cardData['Card']['image'] != '') {
            $availableServers = Configure::read('AVAILABLE_SERVERS');
            foreach ($availableServers as $serverVal) {
                if (substr_count($cardData['Card']['image'], $serverVal) > 0) {
                    $newImgPath = str_replace($serverVal, Configure::read('CURRENT_SERVER'), $cardData['Card']['image']);
                    break;
                }
            }
            ?>
            <br><img src="<?php echo $newImgPath; ?>" width="100">
        <?php } ?>
    </div>
</div>
<div class="form-group">
    <label for="active" class="col-sm-2 control-label">Active</label>
    <div class="col-sm-10">
        <?php
        echo $this->Form->input('active', array('class' => 'form-control', 'label' => false, 'options' => array('no' => 'no', 'yes' => 'yes'),
            'value' => $cardData['Card']['active']));
        ?>
    </div>
</div>
<div class="form-group">
    <div class="col-sm-offset-2 col-sm-10">
        <button type="submit" class="btn btn-info">Update</button>
    </div>
</div>
<?php echo $this->form->end(); ?>
<?php
$cardCatArr = $cardData['Card']['category'];
$cardCatStr = "";
if (count($cardCatArr) > 0) {
    foreach ($cardCatArr as $ccAr) {
        $cardCatStr .= '"' . $ccAr['name'] . '",';
    }
}
$cardCatStr = rtrim($cardCatStr, ', ');
$catStr = '';
if (count($categoryData) > 0) {
    foreach ($categoryData as $ccAr) {
        $catStr .= '"' . $ccAr['Category']['name'] . '", ';
    }
}
$catStr = rtrim($catStr, ', ');
?>
<script type="text/javascript">
    $(document).ready(function() {
        $('#CardEditcardForm').validator();
        $("#CardCategory").val([<?php echo $cardCatStr; ?>]).select2({tags: [<?php echo $catStr; ?>]});
    });
</script>