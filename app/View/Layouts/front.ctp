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
                    <a class="navbar-brand" href="#"><?php echo $this->Html->image('logo-heading.png', array('style'=>"width:120px;")); ?></a>
                </div>
            </div><!-- /.container-fluid -->
        </nav>
        
        <div class="container">
            <?php echo $this->Session->flash(); ?>
            <?php echo $this->fetch('content'); ?>
            <div class="clearfix">&nbsp;</div>
            <div class="clearfix">&nbsp;</div>
        </div>
        
        <div class="footer">
            <div class="containerF">
                <p class="text-muted">Clickin Technologies Ltd. 2014</p>
            </div>
        </div>
    </body>
</html>
