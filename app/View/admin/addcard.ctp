<div class="page-header">
    <h1><?php echo $page_title ?></h1>
</div>
<?php echo $this->Form->create('Card', array('class' => 'form-horizontal', 'method' => 'post', 'enctype' => 'multipart/form-data')); ?>
<div class="form-group">
    <label for="name" class="col-sm-2 control-label">Name</label>
    <div class="col-sm-10">
        <?php echo $this->Form->input('title', array('class' => 'form-control', 'label' => false)); ?>
    </div>
</div>
<div class="form-group">
    <label for="name" class="col-sm-2 control-label">Category</label>
    <div class="col-sm-10">
        <?php echo $this->Form->input('category', array('class' => 'form-control', 'label' => false)); ?>
    </div>
</div>
<div class="form-group">
    <label for="name" class="col-sm-2 control-label">Description</label>
    <div class="col-sm-10">
        <?php echo $this->Form->textarea('description', array('class' => 'form-control', 'label' => false)); ?>
    </div>
</div>
<div class="form-group">
    <label for="name" class="col-sm-2 control-label">Image</label>
    <div class="col-sm-10">
        <?php echo $this->Form->file('image', array('class' => '', 'label' => false)); ?>
    </div>
</div>
<div class="form-group">
    <label for="active" class="col-sm-2 control-label">Active</label>
    <div class="col-sm-10">
        <?php echo $this->Form->input('active', array('class' => 'form-control', 'label' => false, 'options' => array('no' => 'no', 'yes' => 'yes') ));  ?>
    </div>
</div>
<div class="form-group">
    <div class="col-sm-offset-2 col-sm-10">
        <button type="submit" class="btn btn-info">Save</button>
    </div>
</div>
<?php echo $this->form->end(); ?>
<?php 
$catStr = '';
if(count($categoryData)>0) {
    foreach ($categoryData as $ccAr) {
        $catStr .= '"' . $ccAr['Category']['name'] . '", '; 
    }
}
$catStr = rtrim($catStr,', ');

?>

<script type="text/javascript">
$(document).ready(function(){
    $("#CardCategory").select2({tags:[ <?php echo $catStr; ?> ]});
});
</script>