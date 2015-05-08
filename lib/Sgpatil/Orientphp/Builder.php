<?php namespace Sgpatil\Orientphp;

use Closure;

abstract class Builder extends PropertyContainer {

	/**
	 * The database connection instance.
	 *
	 * @var \Illuminate\Database\Connection
	 */
	protected $connection;

	/**
	 * The schema grammar instance.
	 *
	 * @var \Illuminate\Database\Schema\Grammars\Grammar
	 */
	protected $grammar;

	/**
	 * The Blueprint resolver callback.
	 *
	 * @var \Closure
	 */
	protected $resolver;

	/**
	 * Create a new database Schema manager.
	 *
	 * @param  \Illuminate\Database\Connection  $connection
	 * @return void
	 */
	public function __construct(Client $connection)
	{
		$this->connection = $connection;
                parent::__construct($connection);
		//$this->grammar = $connection->getSchemaGrammar();
	}

	/**
	 * Execute the blueprint against the database.
	 *
	 * @param  \Illuminate\Database\Connection  $connection
	 * @param  \Illuminate\Database\Schema\Grammars\Grammar $grammar
	 * @return void
	 */
	public function build(Connection $connection, Grammar $grammar) {
            $node = $connection->getClient()->makeClass($this->table, $this->columns);
            $node->save();
        }

    /**
	 * Get the raw SQL statements for the blueprint.
	 *
	 * @param  \Illuminate\Database\Connection  $connection
	 * @param  \Illuminate\Database\Schema\Grammars\Grammar  $grammar
	 * @return array
	 */
	public function toSql(Connection $connection, Grammar $grammar)
	{
		$this->addImpliedCommands();

		$statements = array();

		// Each type of command has a corresponding compiler function on the schema
		// grammar which is used to build the necessary SQL statements to build
		// the blueprint element, so we'll just call that compilers function.
		foreach ($this->commands as $command)
		{
			$method = 'compile'.ucfirst($command->name);

			if (method_exists($grammar, $method))
			{
				if ( ! is_null($sql = $grammar->$method($this, $command, $connection)))
				{
					$statements = array_merge($statements, (array) $sql);
				}
			}
		}

		return $statements;
	}

	/**
	 * Add the commands that are implied by the blueprint.
	 *
	 * @return void
	 */
	protected function addImpliedCommands()
	{
		if (count($this->columns) > 0 && ! $this->creating())
		{
			array_unshift($this->commands, $this->createCommand('add'));
		}

		$this->addFluentIndexes();
	}

	/**
	 * Add the index commands fluently specified on columns.
	 *
	 * @return void
	 */
	protected function addFluentIndexes()
	{
		foreach ($this->columns as $column)
		{
			foreach (array('primary', 'unique', 'index') as $index)
			{
				// If the index has been specified on the given column, but is simply
				// equal to "true" (boolean), no name has been specified for this
				// index, so we will simply call the index methods without one.
				if ($column->$index === true)
				{
					$this->$index($column->name);

					continue 2;
				}

				// If the index has been specified on the column and it is something
				// other than boolean true, we will assume a name was provided on
				// the index specification, and pass in the name to the method.
				elseif (isset($column->$index))
				{
					$this->$index($column->name, $column->$index);

					continue 2;
				}
			}
		}
	}

	/**
	 * Determine if the blueprint has a create command.
	 *
	 * @return bool
	 */
	protected function creating()
	{
		foreach ($this->commands as $command)
		{
			if ($command->name == 'create') return true;
		}

		return false;
	}

	/**
	 * Indicate that the table needs to be created.
	 *
	 * @return \Illuminate\Support\Fluent
	 */
	public function create()
	{
		return $this->addCommand('create');
	}

	/**
	 * Indicate that the table should be dropped.
	 *
	 * @return \Illuminate\Support\Fluent
	 */
	public function drop()
	{
		return $this->addCommand('drop');
	}

	/**
	 * Indicate that the table should be dropped if it exists.
	 *
	 * @return \Illuminate\Support\Fluent
	 */
	public function dropIfExists()
	{
		return $this->addCommand('dropIfExists');
	}

	/**
	 * Indicate that the given columns should be dropped.
	 *
	 * @param  string|array  $columns
	 * @return \Illuminate\Support\Fluent
	 */
	public function dropColumn($columns)
	{
		$columns = is_array($columns) ? $columns : (array) func_get_args();

		return $this->addCommand('dropColumn', compact('columns'));
	}

	/**
	 * Indicate that the given columns should be renamed.
	 *
	 * @param  string  $from
	 * @param  string  $to
	 * @return \Illuminate\Support\Fluent
	 */
	public function renameColumn($from, $to)
	{
		return $this->addCommand('renameColumn', compact('from', 'to'));
	}

	/**
	 * Indicate that the given primary key should be dropped.
	 *
	 * @param  string|array  $index
	 * @return \Illuminate\Support\Fluent
	 */
	public function dropPrimary($index = null)
	{
		return $this->dropIndexCommand('dropPrimary', 'primary', $index);
	}

	/**
	 * Indicate that the given unique key should be dropped.
	 *
	 * @param  string|array  $index
	 * @return \Illuminate\Support\Fluent
	 */
	public function dropUnique($index)
	{
		return $this->dropIndexCommand('dropUnique', 'unique', $index);
	}

	/**
	 * Indicate that the given index should be dropped.
	 *
	 * @param  string|array  $index
	 * @return \Illuminate\Support\Fluent
	 */
	public function dropIndex($index)
	{
		return $this->dropIndexCommand('dropIndex', 'index', $index);
	}

	/**
	 * Indicate that the given foreign key should be dropped.
	 *
	 * @param  string  $index
	 * @return \Illuminate\Support\Fluent
	 */
	public function dropForeign($index)
	{
		return $this->dropIndexCommand('dropForeign', 'foreign', $index);
	}

	/**
	 * Indicate that the timestamp columns should be dropped.
	 *
	 * @return void
	 */
	public function dropTimestamps()
	{
		$this->dropColumn('created_at', 'updated_at');
	}

	/**
	* Indicate that the soft delete column should be dropped.
	*
	* @return void
	*/
	public function dropSoftDeletes()
	{
		$this->dropColumn('deleted_at');
	}

	/**
	 * Rename the table to a given name.
	 *
	 * @param  string  $to
	 * @return \Illuminate\Support\Fluent
	 */
	public function rename($to)
	{
		return $this->addCommand('rename', compact('to'));
	}

	/**
	 * Specify the primary key(s) for the table.
	 *
	 * @param  string|array  $columns
	 * @param  string  $name
	 * @return \Illuminate\Support\Fluent
	 */
	public function primary($columns, $name = null)
	{
		return $this->indexCommand('primary', $columns, $name);
	}

	/**
	 * Specify a unique index for the table.
	 *
	 * @param  string|array  $columns
	 * @param  string  $name
	 * @return \Illuminate\Support\Fluent
	 */
	public function unique($columns, $name = null)
	{
		return $this->indexCommand('unique', $columns, $name);
	}

	/**
	 * Specify an index for the table.
	 *
	 * @param  string|array  $columns
	 * @param  string  $name
	 * @return \Illuminate\Support\Fluent
	 */
	public function index($columns, $name = null)
	{
		return $this->indexCommand('index', $columns, $name);
	}

	/**
	 * Specify a foreign key for the table.
	 *
	 * @param  string|array  $columns
	 * @param  string  $name
	 * @return \Illuminate\Support\Fluent
	 */
	public function foreign($columns, $name = null)
	{
		return $this->indexCommand('foreign', $columns, $name);
	}

	/**
	 * Create a new auto-incrementing integer column on the table.
	 *
	 * @param  string  $column
	 * @return \Illuminate\Support\Fluent
	 */
	public function increments($column)
	{
		return $this->unsignedInteger($column, true);
	}

	/**
	 * Create a new auto-incrementing big integer column on the table.
	 *
	 * @param  string  $column
	 * @return \Illuminate\Support\Fluent
	 */
	public function bigIncrements($column)
	{
		return $this->unsignedBigInteger($column, true);
	}

	/**
	 * Create a new char column on the table.
	 *
	 * @param  string  $column
	 * @param  int  $length
	 * @return \Illuminate\Support\Fluent
	 */
	public function char($column, $length = 255)
	{
		return $this->addColumn('char', $column, compact('length'));
	}

	/**
	 * Create a new string column on the table.
	 *
	 * @param  string  $column
	 * @param  int  $length
	 * @return \Illuminate\Support\Fluent
	 */
	public function string($column, $length = 255)
	{
		return $this->addColumn('STRING', $column, compact('length'));
	}

	/**
	 * Create a new text column on the table.
	 *
	 * @param  string  $column
	 * @return \Illuminate\Support\Fluent
	 */
	public function text($column)
	{
		return $this->addColumn('text', $column);
	}

	/**
	 * Create a new medium text column on the table.
	 *
	 * @param  string  $column
	 * @return \Illuminate\Support\Fluent
	 */
	public function mediumText($column)
	{
		return $this->addColumn('mediumText', $column);
	}

	/**
	 * Create a new long text column on the table.
	 *
	 * @param  string  $column
	 * @return \Illuminate\Support\Fluent
	 */
	public function longText($column)
	{
		return $this->addColumn('longText', $column);
	}

	/**
	 * Create a new integer column on the table.
	 *
	 * @param  string  $column
	 * @param  bool  $autoIncrement
	 * @param  bool  $unsigned
	 * @return \Illuminate\Support\Fluent
	 */
	public function integer($column, $autoIncrement = false, $unsigned = false)
	{
		return $this->addColumn('INTEGER', $column, compact('autoIncrement', 'unsigned'));
	}

	/**
	 * Create a new big integer column on the table.
	 *
	 * @param  string  $column
	 * @param  bool  $autoIncrement
	 * @param  bool  $unsigned
	 * @return \Illuminate\Support\Fluent
	 */
	public function bigInteger($column, $autoIncrement = false, $unsigned = false)
	{
		return $this->addColumn('bigInteger', $column, compact('autoIncrement', 'unsigned'));
	}

	/**
	 * Create a new medium integer column on the table.
	 *
	 * @param  string  $column
	 * @param  bool  $autoIncrement
	 * @param  bool  $unsigned
	 * @return \Illuminate\Support\Fluent
	 */
	public function mediumInteger($column, $autoIncrement = false, $unsigned = false)
	{
		return $this->addColumn('mediumInteger', $column, compact('autoIncrement', 'unsigned'));
	}

	/**
	 * Create a new tiny integer column on the table.
	 *
	 * @param  string  $column
	 * @param  bool  $autoIncrement
	 * @param  bool  $unsigned
	 * @return \Illuminate\Support\Fluent
	 */
	public function tinyInteger($column, $autoIncrement = false, $unsigned = false)
	{
		return $this->addColumn('tinyInteger', $column, compact('autoIncrement', 'unsigned'));
	}

	/**
	 * Create a new small integer column on the table.
	 *
	 * @param  string  $column
	 * @param  bool  $autoIncrement
	 * @param  bool  $unsigned
	 * @return \Illuminate\Support\Fluent
	 */
	public function smallInteger($column, $length = 255)
	{
		return $this->short($column, $length = 255);
	}
        
        /**
	 * Create a new small integer column on the table.
	 *
	 * @param  string  $column
	 * @param  bool  $autoIncrement
	 * @param  bool  $unsigned
	 * @return \Illuminate\Support\Fluent
	 */
	public function short($column, $length = 255)
	{
		return $this->addColumn('SHORT', $column, compact('length'));
	}
        
        /**
	 * Create a new Long integer column on the table.
	 *
	 * @param  string  $column
	 * @param  bool  $autoIncrement
	 * @param  bool  $unsigned
	 * @return \Illuminate\Support\Fluent
	 */
	public function long($column, $length = 255)
	{
		return $this->addColumn('LONG', $column, compact('length'));
	}

	/**
	 * Create a new unsigned integer column on the table.
	 *
	 * @param  string  $column
	 * @param  bool  $autoIncrement
	 * @return \Illuminate\Support\Fluent
	 */
	public function unsignedInteger($column, $autoIncrement = false)
	{
		return $this->integer($column, $autoIncrement, true);
	}

	/**
	 * Create a new unsigned big integer column on the table.
	 *
	 * @param  string  $column
	 * @param  bool  $autoIncrement
	 * @return \Illuminate\Support\Fluent
	 */
	public function unsignedBigInteger($column, $autoIncrement = false)
	{
		return $this->bigInteger($column, $autoIncrement, true);
	}

	/**
	 * Create a new float column on the table.
	 *
	 * @param  string  $column
	 * @param  int     $total
	 * @param  int     $places
	 * @return \Illuminate\Support\Fluent
	 */
	public function float($column, $total = 8, $places = 2)
	{
		return $this->addColumn('FLOAT', $column, compact('total', 'places'));
	}

	/**
	 * Create a new double column on the table.
	 *
	 * @param  string   $column
	 * @param  int|null	$total
	 * @param  int|null $places
	 * @return \Illuminate\Support\Fluent
	 */
	public function double($column, $total = null, $places = null)
	{
		return $this->addColumn('DOUBLE', $column, compact('total', 'places'));
	}

	/**
	 * Create a new decimal column on the table.
	 *
	 * @param  string  $column
	 * @param  int     $total
	 * @param  int     $places
	 * @return \Illuminate\Support\Fluent
	 */
	public function decimal($column, $total = 8, $places = 2)
	{
		return $this->addColumn('DECIMAL', $column, compact('total', 'places'));
	}

	/**
	 * Create a new boolean column on the table.
	 *
	 * @param  string  $column
	 * @return \Illuminate\Support\Fluent
	 */
	public function boolean($column)
	{
		return $this->addColumn('BOOLEAN', $column);
	}

	/**
	 * Create a new enum column on the table.
	 *
	 * @param  string  $column
	 * @param  array   $allowed
	 * @return \Illuminate\Support\Fluent
	 */
	public function enum($column, array $allowed)
	{
		return $this->addColumn('enum', $column, compact('allowed'));
	}

	/**
	 * Create a new date column on the table.
	 *
	 * @param  string  $column
	 * @return \Illuminate\Support\Fluent
	 */
	public function date($column)
	{
		return $this->addColumn('DATE', $column);
	}

	/**
	 * Create a new date-time column on the table.
	 *
	 * @param  string  $column
	 * @return \Illuminate\Support\Fluent
	 */
	public function dateTime($column)
	{
		return $this->addColumn('DATETIME', $column);
	}

	/**
	 * Create a new time column on the table.
	 *
	 * @param  string  $column
	 * @return \Illuminate\Support\Fluent
	 */
	public function time($column)
	{
		return $this->addColumn('time', $column);
	}

	/**
	 * Create a new timestamp column on the table.
	 *
	 * @param  string  $column
	 * @return \Illuminate\Support\Fluent
	 */
	public function timestamp($column)
	{
		return $this->addColumn('DATETIME', $column);
	}

	/**
	 * Add nullable creation and update timestamps to the table.
	 *
	 * @return void
	 */
	public function nullableTimestamps()
	{
		$this->timestamp('created_at')->nullable();

		$this->timestamp('updated_at')->nullable();
	}

	/**
	 * Add creation and update timestamps to the table.
	 *
	 * @return void
	 */
	public function timestamps()
	{
		$this->timestamp('created_at');

		$this->timestamp('updated_at');
	}

	/**
	 * Add a "deleted at" timestamp for the table.
	 *
	 * @return \Illuminate\Support\Fluent
	 */
	public function softDeletes()
	{
		return $this->timestamp('deleted_at')->nullable();
	}

	/**
	 * Create a new binary column on the table.
	 *
	 * @param  string  $column
	 * @return \Illuminate\Support\Fluent
	 */
	public function binary($column)
	{
		return $this->addColumn('BINARY', $column);
	}

	/**
	 * Add the proper columns for a polymorphic table.
	 *
	 * @param  string  $name
	 * @return void
	 */
	public function morphs($name, $indexName = null)
	{
		$this->unsignedInteger("{$name}_id");

		$this->string("{$name}_type");

		$this->index(array("{$name}_id", "{$name}_type"), $indexName);
	}

	/**
	 * Adds the `remember_token` column to the table.
	 *
	 * @return \Illuminate\Support\Fluent
	 */
	public function rememberToken()
	{
		return $this->string('remember_token', 100)->nullable();
	}

	/**
	 * Create a new drop index command on the blueprint.
	 *
	 * @param  string  $command
	 * @param  string  $type
	 * @param  string|array  $index
	 * @return \Illuminate\Support\Fluent
	 */
	protected function dropIndexCommand($command, $type, $index)
	{
		$columns = array();

		// If the given "index" is actually an array of columns, the developer means
		// to drop an index merely by specifying the columns involved without the
		// conventional name, so we will build the index name from the columns.
		if (is_array($index))
		{
			$columns = $index;

			$index = $this->createIndexName($type, $columns);
		}

		return $this->indexCommand($command, $columns, $index);
	}

	/**
	 * Add a new index command to the blueprint.
	 *
	 * @param  string        $type
	 * @param  string|array  $columns
	 * @param  string        $index
	 * @return \Illuminate\Support\Fluent
	 */
	protected function indexCommand($type, $columns, $index)
	{
		$columns = (array) $columns;

		// If no name was specified for this index, we will create one using a basic
		// convention of the table name, followed by the columns, followed by an
		// index type, such as primary or index, which makes the index unique.
		if (is_null($index))
		{
			$index = $this->createIndexName($type, $columns);
		}

		return $this->addCommand($type, compact('index', 'columns'));
	}

	/**
	 * Create a default index name for the table.
	 *
	 * @param  string  $type
	 * @param  array   $columns
	 * @return string
	 */
	protected function createIndexName($type, array $columns)
	{
		$index = strtolower($this->table.'_'.implode('_', $columns).'_'.$type);

		return str_replace(array('-', '.'), '_', $index);
	}

	/**
	 * Add a new column to the blueprint.
	 * @author Sumit Patil <sgpatil.2803@gmail.com>
	 * @param  string  $type
	 * @param  string  $name
	 * @param  array   $parameters
	 * @return \Illuminate\Support\Fluent
	 */
	protected function addColumn($propertyType, $name, array $parameters = array())
	{
            $attributes = array($name => compact('propertyType'));
            $this->columns[$name] = compact('propertyType');
            return $attributes;
	}

	/**
	 * Remove a column from the schema blueprint.
	 *
	 * @param  string  $name
	 * @return $this
	 */
	public function removeColumn($name)
	{
		$this->columns = array_values(array_filter($this->columns, function($c) use ($name)
		{
			return $c['attributes']['name'] != $name;
		}));

		return $this;
	}

	/**
	 * Add a new command to the blueprint.
	 *
	 * @param  string  $name
	 * @param  array  $parameters
	 * @return \Illuminate\Support\Fluent
	 */
	protected function addCommand($name, array $parameters = array())
	{
		$this->commands[] = $command = $this->createCommand($name, $parameters);

		return $command;
	}

	/**
	 * Create a new Fluent command.
	 *
	 * @param  string  $name
	 * @param  array   $parameters
	 * @return \Illuminate\Support\Fluent
	 */
	protected function createCommand($name, array $parameters = array())
	{
		return new Fluent(array_merge(compact('name'), $parameters));
	}

	/**
	 * Get the table the blueprint describes.
	 *
	 * @return string
	 */
	public function getTable()
	{
		return $this->table;
	}

	/**
	 * Get the columns that should be added.
	 *
	 * @return array
	 */
	public function getColumns()
	{
		return $this->columns;
	}

	/**
	 * Get the commands on the blueprint.
	 *
	 * @return array
	 */
	public function getCommands()
	{
		return $this->commands;
	}
        
        // New Types for orientdb
        
        /**
	 * Create a new binary column on the table.
	 *
	 * @param  string  $column
	 * @return \Illuminate\Support\Fluent
	 */
	public function embedded($column)
	{
		return $this->addColumn('EMBEDDED', $column);
	}
        
        /**
	 * Create a new binary column on the table.
	 *
	 * @param  string  $column
	 * @return \Illuminate\Support\Fluent
	 */
	public function embeddedlist($column)
	{
		return $this->addColumn('EMBEDDEDLIST', $column);
	}
        
        /**
	 * Create a new binary column on the table.
	 *
	 * @param  string  $column
	 * @return \Illuminate\Support\Fluent
	 */
	public function embeddedset($column)
	{
		return $this->addColumn('EMBEDDEDSET', $column);
	}
        
        /**
	 * Create a new binary column on the table.
	 *
	 * @param  string  $column
	 * @return \Illuminate\Support\Fluent
	 */
	public function embeddedmap($column)
	{
		return $this->addColumn('EMBEDDEDMAP', $column);
	}
        
        /**
	 * Create a new binary column on the table.
	 *
	 * @param  string  $column
	 * @return \Illuminate\Support\Fluent
	 */
	public function Link($column)
	{
		return $this->addColumn('LINK', $column);
	}
        
        /**
	 * Create a new binary column on the table.
	 *
	 * @param  string  $column
	 * @return \Illuminate\Support\Fluent
	 */
	public function linklist($column)
	{
		return $this->addColumn('LINKLIST', $column);
	}
        
        /**
	 * Create a new binary column on the table.
	 *
	 * @param  string  $column
	 * @return \Illuminate\Support\Fluent
	 */
	public function linkset($column)
	{
		return $this->addColumn('LINKSET', $column);
	}

        /**
	 * Create a new binary column on the table.
	 *
	 * @param  string  $column
	 * @return \Illuminate\Support\Fluent
	 */
	public function linkmap($column)
	{
		return $this->addColumn('LINKMAP', $column);
	}
        
        /**
	 * Create a new binary column on the table.
	 *
	 * @param  string  $column
	 * @return \Illuminate\Support\Fluent
	 */
	public function byte($column)
	{
		return $this->addColumn('BYTE', $column);
	}
        
        /**
	 * Create a new binary column on the table.
	 *
	 * @param  string  $column
	 * @return \Illuminate\Support\Fluent
	 */
	public function custom($column)
	{
		return $this->addColumn('CUSTOM', $column);
	}
        
        
        /**
	 * Create a new binary column on the table.
	 *
	 * @param  string  $column
	 * @return \Illuminate\Support\Fluent
	 */
	public function linkbag($column)
	{
		return $this->addColumn('LINKBAG', $column);
	}
        
        /**
	 * Create a new binary column on the table.
	 *
	 * @param  string  $column
	 * @return \Illuminate\Support\Fluent
	 */
	public function any($column)
	{
		return $this->addColumn('ANY', $column);
	}
        

}