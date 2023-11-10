Admin Hello World
<form action="{$link->getAdminLink('AdminSyncSevDeskModule')}" method="post">
    <input type="hidden" name="action" value="sendTestInvoice">
    <button type="submit" class="btn btn-default">Test</button>
</form>
