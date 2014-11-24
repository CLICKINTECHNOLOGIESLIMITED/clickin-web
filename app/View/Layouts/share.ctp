<!DOCTYPE html>
<html>
    <head>
        <?php echo $this->Html->charset(); ?>
        <title>Clickin</title>
        <?php
        echo $this->Html->meta('icon');

        echo $this->Html->css('style');
        
        echo $this->fetch('meta');
        echo $this->fetch('css');
        ?>
        <meta name="viewport" content="width=device-width, initial-scale=1">
    </head>
    <body>
        <?php echo $this->fetch('content'); ?>
        
    </body>
</html>
