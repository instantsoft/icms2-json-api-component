<?php
$this->addBreadcrumb(LANG_API_KEYS);
$this->setPageTitle(LANG_API_KEYS);

$this->addToolButton(array(
    'class' => 'add',
    'title' => LANG_ADD,
    'href'  => $this->href_to('keys_add')
));

$this->addToolButton(array(
    'class'  => 'help',
    'title'  => LANG_HELP,
    'target' => '_blank',
    'href'   => LANG_HELP_URL_COM_API
));

$this->renderGrid($this->href_to('keys'), $grid); ?>

<script type="text/javascript">
    $(function(){
        $(document).tooltip({
            items: '.tooltip',
            show: { duration: 0 },
            hide: { duration: 0 },
            position: {
                my: "center",
                at: "top-20"
            }
        });
    });
</script>
