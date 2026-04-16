<?php
/**
 * Post Model
 * Handles all post-related database operations using PDO
 */
class Post {
    private $db;
    private $conn;
    private $table = 'post';

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->connect();
        
        // Try to create table, but don't fail if it already exists
        try {
            $this->createTable();
        } catch (Exception $e) {
            // Log but don't throw - table might already exist
            error_log("Post::createTable warning: " . $e->getMessage());
        }
    }

    /**
     * Create posts table if it doesn't exist
     */
    private function createTable() {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom_auteur VARCHAR(255) NOT NULL,
            titre_post VARCHAR(255) NOT NULL,
            contenu_post TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

        try {
            $this->conn->exec($sql);
        } catch (PDOException $e) {
            // Don't throw, just log
            error_log("Post table creation warning: " . $e->getMessage());
        }
    }

    /**
     * Create a new post using PDO prepared statements
     * @param array $data Post data
     * @return array Response
     */
    public function create($data) {
        $nom_auteur = trim($data['nom_auteur'] ?? '');
        $titre_post = trim($data['titre_post'] ?? '');
        $contenu_post = trim($data['contenu_post'] ?? '');

        // Validate required fields
        if (empty($nom_auteur) || empty($titre_post) || empty($contenu_post)) {
            return [
                'success' => false,
                'error' => 'All fields are required',
                'code' => 'MISSING_FIELDS'
            ];
        }

        // Validate field lengths
        if (strlen($titre_post) > 255) {
            return [
                'success' => false,
                'error' => 'Title must not exceed 255 characters',
                'code' => 'TITLE_TOO_LONG'
            ];
        }

        if (strlen($contenu_post) > 10000) {
            return [
                'success' => false,
                'error' => 'Content must not exceed 10000 characters',
                'code' => 'CONTENT_TOO_LONG'
            ];
        }

        // Insert post using prepared statement (secure against SQL injection)
        $insert_sql = "INSERT INTO {$this->table} (nom_auteur, titre_post, contenu_post) 
                       VALUES (:nom_auteur, :titre_post, :contenu_post)";

        try {
            $stmt = $this->conn->prepare($insert_sql);
            $stmt->execute([
                ':nom_auteur' => $nom_auteur,
                ':titre_post' => $titre_post,
                ':contenu_post' => $contenu_post
            ]);

            return [
                'success' => true,
                'message' => 'Post created successfully',
                'id' => $this->conn->lastInsertId()
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => 'Database error: ' . $e->getMessage(),
                'code' => 'DB_ERROR'
            ];
        }
    }

    /**
     * Get all posts ordered by date using PDO
     * @param int $limit
     * @return array Posts
     */
    public function getAll($limit = 50) {
        $limit = intval($limit);
        $sql = "SELECT id, nom_auteur, titre_post, contenu_post, created_at FROM {$this->table} ORDER BY created_at DESC LIMIT :limit";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $posts ?: [];
        } catch (PDOException $e) {
            error_log("Post::getAll error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a post by ID using PDO
     * @param int $id
     * @return array|null Post data or null
     */
    public function getById($id) {
        $id = intval($id);
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id' => $id]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("Post::getById error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update a post using PDO prepared statements
     * @param int $id
     * @param array $data Post data
     * @return array Response
     */
    public function update($id, $data) {
        $id = intval($id);
        $titre_post = trim($data['titre_post'] ?? '');
        $contenu_post = trim($data['contenu_post'] ?? '');

        // Validate required fields
        if (empty($titre_post) || empty($contenu_post)) {
            return [
                'success' => false,
                'error' => 'All fields are required',
                'code' => 'MISSING_FIELDS'
            ];
        }

        // Validate field lengths
        if (strlen($titre_post) > 255) {
            return [
                'success' => false,
                'error' => 'Title must not exceed 255 characters',
                'code' => 'TITLE_TOO_LONG'
            ];
        }

        if (strlen($contenu_post) > 10000) {
            return [
                'success' => false,
                'error' => 'Content must not exceed 10000 characters',
                'code' => 'CONTENT_TOO_LONG'
            ];
        }

        // Update post using prepared statement
        $update_sql = "UPDATE {$this->table} SET titre_post = :titre_post, contenu_post = :contenu_post WHERE id = :id";

        try {
            $stmt = $this->conn->prepare($update_sql);
            $stmt->execute([
                ':titre_post' => $titre_post,
                ':contenu_post' => $contenu_post,
                ':id' => $id
            ]);

            return [
                'success' => true,
                'message' => 'Post updated successfully'
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => 'Database error: ' . $e->getMessage(),
                'code' => 'DB_ERROR'
            ];
        }
    }

    /**
     * Delete a post using PDO prepared statements
     * @param int $id
     * @return array Response
     */
    public function delete($id) {
        $id = intval($id);

        if (!$id) {
            return [
                'success' => false,
                'error' => 'Post ID is required',
                'code' => 'INVALID_ID'
            ];
        }

        // Delete post using prepared statement
        $delete_sql = "DELETE FROM {$this->table} WHERE id = :id";

        try {
            $stmt = $this->conn->prepare($delete_sql);
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Post deleted successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Post not found',
                    'code' => 'NOT_FOUND'
                ];
            }
        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => 'Database error: ' . $e->getMessage(),
                'code' => 'DB_ERROR'
            ];
        }
    }

    /**
     * Close database connection
     */
    public function __destruct() {
        $this->db->closeConnection();
    }
}
?>
