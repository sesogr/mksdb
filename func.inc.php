<?php
function preventMutationOperations(string $operation, string $tableName)
{
    return $operation === 'list' || $operation === 'read';
}
