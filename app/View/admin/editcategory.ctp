<div class="page-header">
    <h1><?php echo $page_title ?></h1>
</div>
<?php echo $this->Form->create('Category', array('class' => 'form-horizontal', 'method' => 'post')); ?>
<input type="hidden" name="order" value="<?php echo $categoryData['Category']['order']; ?>">
<div class="form-group">
    <label for="name" class="col-sm-2 control-label">Name</label>
    <div class="col-sm-10">
        <?php echo $this->Form->input('name', array('class' => 'form-control', 'label' => false, 'value' => $categoryData['Category']['name'])); ?>
    </div>
</div>
<div class="form-group">
    <label for="active" class="col-sm-2 control-label">Active</label>
    <div class="col-sm-10">
        <?php echo $this->Form->input('active', array('class' => 'form-control', 'label' => false, 'options' => array('no' => 'no', 'yes' => 'yes'), 
            'value' => $categoryData['Category']['active'] ));  ?>
    </div>
</div>
<div class="form-group">
    <div class="col-sm-offset-2 col-sm-10">
        <button type="submit" class="btn btn-info">Update</button>
    </div>
</div>
<?php echo $this->form->end(); ?>



