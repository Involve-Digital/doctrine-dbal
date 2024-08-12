<?php declare(strict_types = 1);

namespace Nettrine\DBAL\Logger;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\SQLParserUtils;
use Doctrine\DBAL\SQLParserUtilsException;

class ProfilerLogger extends AbstractLogger
{

	/** @var Connection */
	protected $connection;

    /** @var string */
    protected $connectionName;

	public function __construct(Connection $connection, string $connectionName = 'default')
	{
		$this->connection = $connection;
        $this->connectionName = $connectionName;
	}

	public function getConnection(): Connection
	{
		return $this->connection;
	}

    public function getConnectionName(): string
    {
        return $this->connectionName;
    }

	/**
	 * @return mixed[]
	 */
	public function getQueries(): array
	{
		return $this->queries;
	}

	public function getTotalTime(): float
	{
		return $this->totalTime;
	}

	/**
	 * @param mixed $sql
	 * @param mixed[] $params
	 * @param mixed[] $types
	 */
	public function startQuery($sql, ?array $params = null, ?array $types = null): void
	{
		if ($params) {
			try {
				/** @var string $sql */
				[$sql, $params, $types] = SQLParserUtils::expandListParameters($sql, $params ?? [], $types ?? []);
			} catch (SQLParserUtilsException $e) {
				// Do nothing
			}

			// Escape % before vsprintf (example: LIKE '%test%')
			// Do not replace ? when it is used in regex
			$sql = preg_replace_callback(
				'/\'[^\']*\?[^\']*\'/',
				function ($matches) {
					return str_replace('?', '---#---', $matches[0]);
				},
				$sql
			);
			$sql = str_replace(['%', '?'], ['%%', '%s'], $sql);
			$sql = str_replace('---#---', '?', $sql);

			$query = vsprintf(
				$sql,
				call_user_func(function () use ($params, $types) {
					$quotedParams = [];
					foreach ($params as $typeIndex => $value) {
						$type = $types[$typeIndex] ?? null;
						$quotedParams[] = $value === null ? $value : $this->connection->quote($value, $type);
					}

					return $quotedParams;
				})
			);
		} else {
			$query = $sql;
		}

		parent::startQuery($query, $params, $types);
	}

}
