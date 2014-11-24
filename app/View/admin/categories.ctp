<div class="page-header">
    <h1><?php echo $page_title ?></h1>
</div>
<?php echo $this->Html->link('Add Category', array('action' => 'addcategory', 'full_base' => true)); ?>
<div id="debugArea"></div>
<div class="table-responsive">
    <table border="0" id="table-1" cellspacing="0" cellpadding="0" class="table-condensed table-bordered table-striped table table-hover">
        <tr>
            <th class="sort-td">Name</th>
            <th class="sort-td">Active</th>
            <th class="sort-td">Order</th>
            <th class="sort-td" style="width:9%;">Action</th>
        </tr>
        <?php if (count($categories) > 0) { ?>
            <?php foreach ($categories as $catArr) { ?>
                <tr id="<?php echo $catArr['Category']['_id'] ?>">
                    <td><?php echo $catArr['Category']['name'] ?> </td>
                    <td><?php echo $catArr['Category']['active'] ?></td>
                    <td><?php echo $catArr['Category']['order'] ?></td>
                    <td>
                        <?php echo $this->Html->link('Edit', array('action' => 'editcategory', 'id' => (string) $catArr['Category']['_id'], 'full_base' => true)); ?>
                    </td>
                </tr>    
            <?php } 
            } else { ?>
            <tr>
                <td colspan="4">No record found.</td>
            </tr>    
        <?php } ?>
    </table>
</div>
<script  type="text/javascript">
$(document).ready(function() {
 
    // Initialise the second table specifying a dragClass and an onDrop function that will display an alert
    $("#table-1").tableDnD({
        onDragClass: "myDragClass",
        onDrop: function(table, row) {
            var rows = table.tBodies[0].rows;
            var debugStr = ""; //Row dropped was "+row.id+". New order: ";
            for (var i=0; i<rows.length; i++) {
                debugStr += rows[i].id+"##";
            }
            //$('#debugArea').html(debugStr);
            $.post("<?php echo $this->Html->url('/');?>admin/ordercategories",{displayOrders : debugStr},function(d,s){
                location.href = location.href;
            },'html');
        },
        onDragStart: function(table, row) {
            //$('#debugArea').html("Started dragging row "+row.id);
        }
    });
    // Make a nice striped effect on the table
    //$('#table-1 tr:even').addClass('alt');
 
});
</script>
