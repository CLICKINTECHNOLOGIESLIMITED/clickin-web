<!DOCTYPE html>
<html>
    <head>
        <?php echo $this->Html->charset(); ?>
        <title>
            Clickin :: <?php echo $title_for_layout; ?>
        </title>
        <script src="//code.jquery.com/jquery-1.11.0.min.js"></script>
        <?php
        echo $this->Html->meta('icon');

        echo $this->Html->script('bootstrap');
        echo $this->Html->css('bootstrap');

        echo $this->Html->script('jquery.tablednd');

        echo $this->Html->script('select2.min');
        echo $this->Html->script('validator');
        echo $this->Html->css('select2');
        echo $this->Html->css('select2-bootstrap');

        echo $this->fetch('meta');
        echo $this->fetch('css');
        echo $this->fetch('script');
        ?>
        <meta name="viewport" content="width=device-width, initial-scale=1">
    </head>
    <body>

        <nav role="navigation" class="navbar navbar-inverse">
            <div class="container-fluid">
                <!-- Brand and toggle get grouped for better mobile display -->
                <div class="navbar-header">
                    <button data-target="#bs-example-navbar-collapse-9" data-toggle="collapse" class="navbar-toggle collapsed" type="button">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>
                    <a class="navbar-brand" href="<?php echo $this->Html->url('dashboard'); ?>">
                        <?php echo $this->Html->image('logo-heading.png', array('style' => "width:120px;")); ?> Administration</a>
                </div>

                <!-- Collect the nav links, forms, and other content for toggling -->
                <div id="bs-example-navbar-collapse-9" class="navbar-collapse collapse" style="height: 1px;">
                    <ul class="nav navbar-nav">
                        <?php if ($this->Session->read('User.loginUserID') != '') { ?>
                            <li><?php echo $this->Html->link('Categories', array('action' => 'categories', 'full_base' => true)); ?></li>
                            <li><?php echo $this->Html->link('Cards', array('action' => 'cards', 'full_base' => true)); ?></li>
                            <li><?php echo $this->Html->link('Users', array('action' => 'users', 'full_base' => true)); ?></li>
                            <li><?php echo $this->Html->link('Reported Newsfeeds', array('action' => 'reportedinappropriatenewsfeeds', 'full_base' => true)); ?></li>
                            <li><?php echo $this->Html->link('Reported Problems', array('action' => 'reportedproblems', 'full_base' => true)); ?></li>
                            <?php /* li><?php echo $this->Html->link('Change Password', array('action' => 'changepassword', 'full_base' => true)); ?></li */ ?>
                            <li><?php echo $this->Html->link('Logout', array('action' => 'logout', 'full_base' => true)); ?></li>
                        <?php } ?>
                    </ul>
                </div><!-- /.navbar-collapse -->
            </div><!-- /.container-fluid -->
        </nav>

        <div class="container">
            <?php echo $this->Session->flash(); ?>
            <?php echo $this->fetch('content'); ?>
            <div class="clearfix">&nbsp;</div>
            <?php //echo $this->element('sql_dump'); ?>
            <div class="clearfix">&nbsp;</div>
        </div> 

        <div class="footer">
            <div class="containerF">
                <p class="text-muted">Clickin Technologies Ltd. 2014</p>
            </div>
        </div>
    </body>
</html>
