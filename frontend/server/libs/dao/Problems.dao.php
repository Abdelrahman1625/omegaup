<?php

require_once("base/Problems.dao.base.php");
require_once("base/Problems.vo.base.php");
/** Page-level DocBlock .
  * 
  * @author alanboy
  * @package docs
  * 
  */
/** Problems Data Access Object (DAO).
  * 
  * Esta clase contiene toda la manipulacion de bases de datos que se necesita para 
  * almacenar de forma permanente y recuperar instancias de objetos {@link Problems }. 
  * @author alanboy
  * @access public
  * @package docs
  * 
  */
class ProblemsDAO extends ProblemsDAOBase
{
	public static final function byUserType($user_type, $order, $mode, $offset, $rowcount, $query, $user_id = null) {
		global $conn;
		$result = null;
		if ($user_type === USER_ADMIN) {
			$like_query = '';
			if (!is_null($query)) {
				$escapedValue = mysql_real_escape_string($query);
				$like_query = " WHERE title like '%{$escapedValue}%'";
			}
			$sql = "
				SELECT
					*
				FROM
					(
						(
							SELECT
								R.score, P.*
							FROM
								(
									SELECT
										user_id,
										problem_id,
										ROUND(MAX(score) * 100, 2) AS score
									FROM
										Runs 
									WHERE
										status = 'ready' AND
										user_id = $user_id
									GROUP BY
										user_id,
										problem_id
								) AS R,
								(
									SELECT
										100 / LOG2(GREATEST(accepted, 1) + 1)   AS points,
										accepted / GREATEST(1, submissions)     AS ratio,
										Problems.*
									FROM
										Problems
								) AS P
							WHERE
								R.problem_id = P.problem_id AND
								R.user_id = $user_id
						)
						UNION
						(
								SELECT
									0 AS score,
									100 / LOG2(GREATEST(accepted, 1) + 1)   AS points,
									accepted / GREATEST(1, submissions)     AS ratio,
									Problems.*
								FROM
									Problems
								WHERE
									(problem_id, $user_id) NOT IN
										(
											SELECT
												problem_id,
												user_id
											FROM
												Runs 
											WHERE
												status = 'ready' AND
												user_id = $user_id
											GROUP BY
												user_id,
												problem_id
										)
						)
					) AS T
				$like_query
				ORDER BY
					$order $mode";

			if (!is_null($rowcount)) {
				$sql .= " LIMIT $offset, $rowcount";
			}	

			$result = $conn->Execute($sql);
		} else if ($user_type === USER_NORMAL && !is_null($user_id)) {
			$like_query = '';
			if (!is_null($query)) {
				$escapedValue = mysql_real_escape_string($query);
				$like_query = " WHERE title LIKE '%{$escapedValue}%'";
			}
			$sql = "
				SELECT
					*
				FROM
					(
						(
							SELECT
								R.score, P.*
							FROM
								(
									SELECT
										user_id,
										problem_id,
										ROUND(MAX(score) * 100, 2) AS score
									FROM
										Runs 
									WHERE
										status = 'ready' AND
										user_id = $user_id
									GROUP BY
										user_id,
										problem_id
								) AS R,
								(
									SELECT
										100 / LOG2(GREATEST(accepted, 1) + 1)   AS points,
										accepted / GREATEST(1, submissions)     AS ratio,
										Problems.*
									FROM
										Problems
									WHERE
										(public = 1 OR author_id = $user_id)
								) AS P
							WHERE
								R.problem_id = P.problem_id AND
								R.user_id = $user_id
						)
						UNION
						(
								SELECT
									0 AS score,
									100 / LOG2(GREATEST(accepted, 1) + 1)   AS points,
									accepted / GREATEST(1, submissions)     AS ratio,
									Problems.*
								FROM
									Problems
								WHERE
									(public = 1 OR author_id = $user_id) AND
									(problem_id, $user_id) NOT IN
										(
											SELECT
												problem_id,
												user_id
											FROM
												Runs 
											WHERE
												status = 'ready' AND
												user_id = $user_id
											GROUP BY
												user_id,
												problem_id
										)
						)
					) AS T
				$like_query
				ORDER BY
					$order $mode";

			if (!is_null($rowcount)) {
				$sql .= " LIMIT $offset, $rowcount";
			}	
			$result = $conn->Execute($sql);
		} else if ($user_type === USER_ANONYMOUS) {
			$like_query = "";
			if (!is_null($query)) {
				$escapedValue = mysql_real_escape_string($query);
				$like_query = " AND title LIKE '%{$escapedValue}%'";
			}
			$sql = "SELECT	0 AS score,
							100 / LOG2(GREATEST(accepted, 1) + 1) AS points,
							accepted / GREATEST(1, submissions)   AS ratio,
							Problems.*
					FROM	Problems
					WHERE 	public = 1 $like_query ORDER BY $order $mode";

			if (!is_null($rowcount)) {
				$sql .= " LIMIT $offset, $rowcount";
			}	

			$result = $conn->Execute($sql);
		}

		$problems = array();
		if (!is_null($result)) {
			foreach ($result as $row) {
				$temp = new Problems($row);
				$problem = $temp->asArray();
				$problem['score'] = $row['score'];
				$problem['points'] = $row['points'];
				$problem['ratio'] = $row['ratio'];
				array_push($problems, $problem);
			}
		}
		return $problems;
	}

	/* byPage: search and return all the results by size and number of page and other attributes */
	public static final function byPage( $sizePage , $noPage , $condition = null , $serv = null, $orderBy = null, $orden = 'ASC')
	{	
		global $conn;
		$val = array($condition);		
		$sql = "SELECT count(*) from Problems WHERE $condition"; 	
		$rs = $conn->getRow($sql);
		$total = $rs[0];
		
		//problem_id	public	author_id	title	alias	validator	server	remote_id	
		//time_limit	memory_limit	visits	submissions	accepted	difficulty	creation_date	source	order
		$ord = array("problem_id"=>1,
					 "public"=>0,
					 "author_id"=>0,
					 "title"=>1,
					 "alias"=>0,
					 "validator"=>0,
					 "server"=>0,
					 "remote_id"=>0,
					 "time_limit"=>1,
					 "memory_limit"=>1,
					 "visits"=>1,
					 "submissions"=>1,
					 "accepted"=>1,
					 "difficulty"=>1,
					 "creation_date"=>0,
					 "source"=>0,
					 "order"=>0
					 );					 
		if(!isset($ord[$orderBy]))
			$orderBy = "title";
		else if($ord[$orderBy] == 0)$orderBy = "title";
		
		$total_paginas = ceil($total/$sizePage);
		$inicio = $sizePage * ($noPage - 1);
		$limite = $inicio + $sizePage;
		$val = array(  $inicio, $sizePage);		
		$sql = "SELECT * from Problems WHERE $condition ORDER BY $orderBy $orden LIMIT ?, ?"; 
		
		$rs = $conn->Execute($sql, $val);
		$ar = array();
		$info = "Mostrando $noPage de $total_paginas p&aacute;ginas. De ".( $inicio + 1 )." 
		a $limite de  $total registros.";
		$bar_size = 5;//sera 1 mas
		
		if( ( $noPage - $bar_size/2 ) >= ($total_paginas  - $bar_size )) $i = $total_paginas - $bar_size;		
		else if( ( $noPage - $bar_size/2 )>1)$i = ceil($noPage - $bar_size/2);
		else $i=1;
		$cont = 0;
		$bar = "<a href='?noPage=1&serv=$serv&order=$orderBy'> << </a>";
		for(;$i <= $total_paginas && $cont <= $bar_size ; $i++  ){
			if($noPage == $i) $bar .= " $i ";
			else $bar .= "<a href='?noPage=$i&serv=$serv&order=$orderBy'> $i </a>";
			$cont ++;
		}
		$bar .= "<a href='?noPage=$total_paginas&serv=$serv&order=$orderBy'> >> </a>";
		array_push( $ar, $info);
		array_push( $ar, $bar);
		foreach ($rs as $foo) {		
    		array_push( $ar, new Problems($foo));
		}
		return $ar;
	}
	
	public static final function getByAlias($alias)
	{
		$sql = "SELECT * FROM Problems WHERE (alias = ? ) LIMIT 1;";
		$params = array(  $alias );
                
		global $conn;
		$rs = $conn->GetRow($sql, $params);
		if(count($rs)==0)
                {
                    return NULL;
                }
                
                $contest = new Problems( $rs );

                return $contest;
	}

	public static final function searchByAlias($alias)
	{
		global $conn;
		$quoted = $conn->Quote($alias);
		
		if (strpos($quoted, "'") !== FALSE) {
			$quoted = substr($quoted, 1, strlen($quoted) - 2);
		}

		$sql = "SELECT * FROM Problems WHERE (alias LIKE '%$quoted%' OR title LIKE '%$quoted%') LIMIT 0,10;";
		$rs = $conn->Execute($sql);

		$result = array();

		foreach ($rs as $r) {
			array_push($result, new Problems($r));
		}

		return $result;
	}

	public static final function getPracticeDeadline($id) {
		global $conn;

		$sql = "SELECT COALESCE(UNIX_TIMESTAMP(MAX(finish_time)), 0) FROM Contests c INNER JOIN Contest_Problems cp USING(contest_id) WHERE cp.problem_id = ?";
		return $conn->GetOne($sql, $id);
	}
	
	public static final function getProblemsSolved($id) {
		global $conn;
		
		$sql = "SELECT DISTINCT `Problems`.* FROM `Problems` INNER JOIN `Runs` ON `Problems`.problem_id = `Runs`.problem_id WHERE `Runs`.veredict = 'AC' and `Runs`.test = 0 and `Runs`.user_id = ? ORDER BY `Problems`.problem_id DESC";
		$val = array($id);
		$rs = $conn->Execute($sql, $val);
		
		$result = array();

		foreach ($rs as $r) {
			array_push($result, new Problems($r));
		}

		return $result;		
	}
	
	public static function getPrivateCount(Users $user) {
		$sql = "SELECT count(*) as Total FROM Problems WHERE public = 0 and (author_id = ?);";		
		$params = array($user->getUserId());
                
		global $conn;
		$rs = $conn->GetRow($sql, $params);				                        
		
		if (!array_key_exists("Total", $rs)) {
			return 0;
		}
 		
        return $rs["Total"];
	}
}
