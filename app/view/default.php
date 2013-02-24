<?php if($report):?>
<div class="report"><?php print$report?></div>
<?php endif; ?>

<?php if($is_logged):?>
    <div>Hello <?php print$username?></div>
    <div><a href="/index.php?q=default/default/logout">Logout</a></div>
<?php else:?>
    <div>Hello World!</div>
        <form method="POST" action="/index.php?q=default/default/login">
            <input type="text" name="username" />
            <input type="password" name="pass" />
            <input type="submit" value="Enter" />
        </form>
<?php endif;?>
