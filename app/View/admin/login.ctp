<div class="page-header">
  <h1><?php echo $page_title ?></h1>
</div>
<?php echo $this->Form->create('Admin', array('class' => 'form-horizontal', 'method'=> 'post')); ?>
    <div class="form-group">
        <label for="username" class="col-sm-2 control-label">Username</label>
        <div class="col-sm-10">
            <input type="text" id="username" name="data[Admin][username]" placeholder="Username" value="" class="form-control">
        </div>
    </div>
    <div class="form-group">
        <label for="password" class="col-sm-2 control-label">Password</label>
        <div class="col-sm-10">
            <input type="password" id="password" name="data[Admin][password]" placeholder="Password" value="" class="form-control">
        </div>
    </div>
    <div class="form-group">
        <div class="col-sm-offset-2 col-sm-10">
            <button type="submit" class="btn btn-info">Login</button>
        </div>
    </div>
<?php echo $this->form->end(); ?>



