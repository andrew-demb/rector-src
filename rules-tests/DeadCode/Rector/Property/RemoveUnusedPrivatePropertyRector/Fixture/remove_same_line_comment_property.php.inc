<?php

namespace Rector\Tests\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector\Fixture;

final class RemoveSamelineCommentProperty
{

    protected $_distributoradaids = [];

    private $_max_fileage = 3600;// 1h
}

?>
-----
<?php

namespace Rector\Tests\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector\Fixture;

final class RemoveSamelineCommentProperty
{

    protected $_distributoradaids = [];
}

?>