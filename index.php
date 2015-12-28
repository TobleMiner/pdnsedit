<html>
    <head>
        <meta charset="utf-8">
        <title>PDNS editor</title>
    </head>
    <body>
<?php
    require_once('dns.php');

    use \DNS\Domain as Domain;

    function show_error($msg)
    {
        ?>
        <h2 style="color: #da2525;"><?= $msg?></h2>
        <?php
    }

    function show_add_domain()
    {
        ?>
        <form method="post" action="#">
            <input type="hidden" name="action" value="add">
            <input type="text" name="domain">
            <input type="submit" value="Add">
        </form>
        <?php
    }

    function show_domains()
    {
        ?>
        <table border="1">
            <tr>
                <th>Domain</th>
                <th>Edit</th>
                <th>Delete</th>
            </tr>
        <?php
            foreach (Domain::get_all() as $domain)
            {
                ?>
                <tr>
                    <td><?= $domain->domain?></td>
                    <td>
                        <form method="get" action="#">
                            <input type="hidden" name="id" value="<?= $domain->id?>">
                            <input type="hidden" name="action" value="edit">
                            <input type="submit" value="Edit">
                        </form>
                    </td>
                    <td>
                        <form method="post" action="#">
                            <input type="hidden" name="id" value="<?= $domain->id?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="submit" value="Delete">
                        </form>
                    </td>
                </tr>
                <?php
            }
        ?>
        </table>
        <?php
    }

    function show_add_record($domain)
    {
        ?>
        <form method="post" action="#">
            <input type="hidden" name="id" value="<?= $domain->id?>">
            <input type="hidden" name="action" value="add_record">
            <table border="1">
                <tr>
                    <th>Domain</th>
                    <th>Name</th>
                    <th>Record</th>
                    <th>Value</th>
                    <th>TTL</th>
                    <th>Priority</th>
                    <th></th>
                </tr>
                <tr>
                    <td><?= $domain->domain?></td>
                    <td>
                        <input type="text" name="name">
                    </td>
                    <td>
                        <select name="type">
                            <?php foreach (\DNS\Record::get_record_types() as $type): ?>
                                <option value="<?= $type?>"><?= $type?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <input type="text" name="value">
                    </td>
                    <td>
                        <input type="number" name="ttl" value="3600">
                    </td>
                    <td>
                        <input type="number" name="priority" value="0">
                    </td>
                    <td>
                        <input type="submit" value="Add">
                    </td>
                </tr>
            </table>
        </form>
        <?php
    }

    function show_records($domain)
    {
        ?>
        <h1><?= $domain->domain?></h1>
        <table border="1">
            <tr>
                <th>Name</th>
                <th>Type</th>
                <th>Value</th>
                <th>TTL</th>
                <th>Priority</th>
                <th></th>
                <th></th>
            </tr>
        <?php
            foreach ($domain->get_all_records() as $record)
            {
                ?>
                <tr>
                    <form method="post" action="#">
                        <input type="hidden" name="id" value="<?= $record->id?>">
                        <input type="hidden" name="action" value="save_record">
                        <td>
                            <input type="text" name="name" value="<?= $record->name?>">
                        </td>
                        <td>
                            <select name="type">
                                <?php foreach (\DNS\Record::get_record_types() as $type): ?>
                                    <option value="<?= $type?>" <?= $record->type == $type ? 'selected' : ''?>><?= $type?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input type="text" name="value" value="<?= $record->value?>">
                        </td>
                        <td>
                            <input type="number" name="ttl" value="<?= $record->ttl?>">
                        </td>
                        <td>
                            <input type="number" name="priority" value="<?= $record->priority?>">
                        </td>
                        <td>
                            <input type="submit" value="Save">
                        </td>
                    </form>
                    <td>
                        <form method="post" action="#">
                            <input type="hidden" name="id" value="<?= $record->id?>">
                            <input type="hidden" name="action" value="delete_record">
                            <input type="submit" value="Delete">
                        </form>
                    </td>
                </tr>
                <?php
            }
        ?>
        </table>
        <?php
    }

    if(!(array_key_exists('action', $_POST)
        || array_key_exists('action', $_GET)))
    {
        show_add_domain();
        show_domains();
    }
    elseif(array_key_exists('action', $_POST))
    {
        switch ($_POST['action'])
        {
            case 'add':
                if(!array_key_exists('domain', $_POST)) die('Missing domain');
                (new Domain(-1, $_POST['domain']))->add();
                show_add_domain();
                show_domains();
                break;
            case 'delete':
                if(!array_key_exists('id', $_POST)) die('Missing id');
                (new Domain($_POST['id']))->delete();
                show_add_domain();
                show_domains();
                break;
            case 'add_record':
                if(!array_key_exists('id', $_POST)) die('Missing id');
                if(!array_key_exists('name', $_POST)) die('Missing record name');
                if(!array_key_exists('type', $_POST)) die('Missing record type');
                if(!array_key_exists('value', $_POST)) die('Missing record value');
                if(!array_key_exists('ttl', $_POST)) die('Missing record ttl');
                if(!array_key_exists('priority', $_POST)) die('Missing record priority');
                if(!in_array($_POST['type'], \DNS\Record::get_record_types()))
                    die('Invalid record type');
                $domain = new Domain($_POST['id']);
                $classname = '\\DNS\\'.$_POST['type'].'_record';
                $record = new $classname($domain, -1, $_POST['name'], $_POST['value'],
                    $_POST['ttl'], $_POST['priority']);
                if($record->validate())
                    $record->add();
                else
                    show_error($record->error);
                show_add_record($domain);
                show_records($domain);
                break;
            case 'save_record':
                if(!array_key_exists('id', $_POST)) die('Missing id');
                if(!array_key_exists('name', $_POST)) die('Missing record name');
                if(!array_key_exists('type', $_POST)) die('Missing record type');
                if(!array_key_exists('value', $_POST)) die('Missing record value');
                if(!array_key_exists('ttl', $_POST)) die('Missing record ttl');
                if(!array_key_exists('priority', $_POST)) die('Missing record priority');
                if(!in_array($_POST['type'], \DNS\Record::get_record_types()))
                    die('Invalid record type');
                $record = \DNS\Record::from_id($_POST['id']);
                $record->name = $_POST['name'];
                $record->type = $_POST['type'];
                $record->value = $_POST['value'];
                $record->ttl = $_POST['ttl'];
                $record->priority = $_POST['priority'];
                if($record->validate())
                    $record->save();
                else
                    show_error($record->error);
                show_add_record($record->domain);
                show_records($record->domain);
                break;
            case 'delete_record':
                if(!array_key_exists('id', $_POST)) die('Missing id');
                $record = \DNS\Record::from_id($_POST['id']);
                $record->delete();
                show_add_record($record->domain);
                show_records($record->domain);
                break;
            default:
                die('Unknown action');
                break;
        }
    }
    else
    {
        switch ($_GET['action'])
        {
            case 'edit':
                if(!array_key_exists('id', $_GET)) die('Missing id');
                $domain = new Domain($_GET['id']);
                show_add_record($domain);
                show_records($domain);
                break;
        }
    }
?>
    </body>
</html>
