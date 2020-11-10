<?php

namespace Coderello\PopulatedFactory;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class FactoryGenerator
{
    const TAB = '    ';

    const NL = PHP_EOL;

    protected $guesser;

    protected $columnShouldBeIgnored;

    protected $appendFactoryPhpDoc = true;

    public function __construct(FakeValueExpressionGuesser $guesser, ColumnShouldBeIgnored $columnShouldBeIgnored)
    {
        $this->guesser = $guesser;

        $this->columnShouldBeIgnored = $columnShouldBeIgnored;
    }

    public function generate(Model $model): string
    {
        $this->setConnection($model);
        
        $table = $this->table($model);

        $columns = $this->columns($table);

        $modelNamespace = get_class($model);

        $modelClassName = class_basename($model);

        $definition = collect($columns)
            ->map(function (Column $column) {
                if (($this->columnShouldBeIgnored)($column)) {
                    return null;
                }

                if (is_null($value = $this->guessValue($column))) {
                    return null;
                }

                return str_repeat(self::TAB, 3).'\''.$column->getName().'\' => '.$value.',';
            })
            ->filter()
            ->implode(self::NL);

        return <<<FACTORY
<?php

use Faker\Generator as Faker;

/** @var \Illuminate\Database\Eloquent\Factory \$factory */

\$factory->define({$modelNamespace}::class, function (Faker \$faker) {
        return [
{$definition}
        ];
});
FACTORY;
    }

    protected function table(Model $model): Table
    {
        $schemaManager = $model->getConnection()
            ->getDoctrineSchemaManager();

        $schemaManager->getDatabasePlatform()
            ->registerDoctrineTypeMapping('enum', 'string');

        return $schemaManager->listTableDetails($model->getTable());
    }

    /**
     * @param Table $table
     * @return Column[]|array
     */
    protected function columns(Table $table): array
    {
        return $table->getColumns();
    }

    protected function guessValue(Column $column)
    {
        return $this->guesser->guess($column);
    }
    
    protected function setConnection(Model $model)
    {
        $this->connection = $model->getConnection();
    }
}
