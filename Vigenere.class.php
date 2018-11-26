<?php
class Vigenere
{
    public function Mod($a, $b){
        return ($a % $b + $b) % $b;
    }
    public function cipher($input, $key, $encipher){
        $keyLen = strlen($key);

        for ($i = 0; $i < $keyLen; ++$i)
            if (!ctype_alpha($key[$i]))
                return ""; // Error

        $output = "";
        $nonAlphaCharCount = 0;
        $inputLen = strlen($input);

        for ($i = 0; $i < $inputLen; ++$i)
        {
            if (ctype_alpha($input[$i]))
            {
                $cIsUpper = ctype_upper($input[$i]);
                $offset = ord($cIsUpper ? 'A' : 'a');
                $keyIndex = ($i - $nonAlphaCharCount) % $keyLen;
                $k = ord($cIsUpper ? strtoupper($key[$keyIndex]) : strtolower($key[$keyIndex])) - $offset;
                $k = $encipher ? $k : -$k;
                $ch = chr(($this->Mod(((ord($input[$i]) + $k) - $offset), 26)) + $offset);
                $output .= $ch;
            }
            else
            {
                $output .= $input[$i];
                ++$nonAlphaCharCount;
            }
        }

        return $output;
    }
    public function encrypt($input, $key, $times = 1){
        $str = $input;
        $count = 0;
        while ($count < $times) {
            $str = $this->cipher($str, $key, true);
            $count++;
        }
        return $str;
    }
    public function decrypt($input, $key){
        $str = $input;
        $count = 0;
        while ($count < $times) {
            $str = $this->cipher($str, $key, false);
            $count++;
        }
        return $str;
    }
}