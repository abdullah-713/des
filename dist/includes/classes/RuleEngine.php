<?php
/**
 * فئة محرك القواعد الديناميكية
 */
class RuleEngine {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function evaluate($ruleKey, $variables = []) {
        $stmt = $this->db->prepare("SELECT equation, is_active FROM dynamic_rules WHERE rule_key = ?");
        $stmt->execute([$ruleKey]);
        $rule = $stmt->fetch();

        if (!$rule || !$rule['is_active']) {
            return null;
        }

        $equation = $rule['equation'];
        return $this->evaluateExpression($equation, $variables);
    }

    private function evaluateExpression($expression, $variables) {
        foreach ($variables as $key => $value) {
            $numericValue = is_numeric($value) ? $value : 0;
            $expression = str_replace($key, $numericValue, $expression);
        }

        $sanitized = preg_replace('/[^0-9\.\+\-\*\/\(\)\?\:\<\>\=\s]/', '', $expression);

        if (empty($sanitized)) return 0;

        try {
            $result = @eval("return $sanitized;");
            if ($result === false && ($sanitized != "return ;")) {
                logError("Rule Evaluation Error: Invalid expression $sanitized");
                return 0;
            }
            return $result;
        } catch (Throwable $e) {
            logError("Rule Evaluation Exception: " . $e->getMessage());
            return 0;
        }
    }
}
