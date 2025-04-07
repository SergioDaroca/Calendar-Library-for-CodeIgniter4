<?php

/**
 * CodeIgniter Calendar Class PHP 8.* compatible
 *
 * This class enables the creation of calendars
 *
 * @package		CodeIgniter (Hopefully somebody will integrate this in the framework's library after testing, revision, documentation...)
 * @subpackage	Libraries
 * @category	Libraries
 * @author		Sergio Daroca with help from Deepseek (or Deepseek with help of Sergio Daroca), based entirely on original library from ExpressionEngine Dev Team
 *
 */

namespace App\Libraries;

use CodeIgniter\I18n\Time;

class CI_4_Calendar
{
    protected $template = '';
    protected $startDay = 'monday';
    protected $monthType = 'long';
    protected $dayType = 'abr';
    protected $showNextPrev = false;
    protected $nextPrevUrl = '';
    protected $localTime;
    
    protected $temp = [];
    
    /**
     * Constructor
     */
    public function __construct(array $config = [])
    {
        $this->localTime = Time::now()->getTimestamp();
        
        if (!empty($config)) {
            $this->initialize($config);
        }
    }
    
    /**
     * Initialize the user preferences
     */
    public function initialize(array $config): self
    {
        foreach ($config as $key => $val) {
            if (isset($this->$key)) {
                $this->$key = $val;
            }
        }
        
        return $this;
    }
    
    /**
     * Generate the calendar
     */
    public function generate(?int $year = null, ?int $month = null, array $data = []): string
    {
        // Set and validate the supplied month/year
        $year = $year ?? (int)date('Y', $this->localTime);
        $month = $month ?? (int)date('m', $this->localTime);
        
        // Adjust month/year if needed
        $adjustedDate = $this->adjustDate($month, $year);
        $month = (int)$adjustedDate['month'];
        $year = (int)$adjustedDate['year'];
        
        // Determine the total days in the month
        $totalDays = $this->getTotalDays($month, $year);
        
        // Set the starting day of the week
        $startDays = [
            'sunday' => 0, 
            'monday' => 1, 
            'tuesday' => 2, 
            'wednesday' => 3, 
            'thursday' => 4, 
            'friday' => 5, 
            'saturday' => 6
        ];
        
        $startDay = $startDays[$this->startDay] ?? 0;
        
        // Set the starting day number
        $localDate = mktime(12, 0, 0, $month, 1, $year);
        $date = getdate($localDate);
        $day = $startDay + 1 - $date['wday'];
        
        while ($day > 1) {
            $day -= 7;
        }
        
        // Set the current month/year/day
        $curYear = (int)date('Y', $this->localTime);
        $curMonth = (int)date('m', $this->localTime);
        $curDay = (int)date('j', $this->localTime);
        
        $isCurrentMonth = ($curYear === $year && $curMonth === $month);
        
        // Generate the template data array
        $this->parseTemplate();
        
        // Begin building the calendar output                        
        $out = $this->temp['table_open'];
        $out .= "\n";        
        $out .= $this->temp['heading_row_start'];
        $out .= "\n";
        
        // "previous" month link
        if ($this->showNextPrev) {
            // Add a trailing slash to the URL if needed
            $this->nextPrevUrl = preg_replace("/(.+?)\/*$/", "\\1/", $this->nextPrevUrl);
            
            $adjustedDate = $this->adjustDate($month - 1, $year);
            $out .= str_replace(
                '{previous_url}', 
                $this->nextPrevUrl.$adjustedDate['year'].'/'.$adjustedDate['month'], 
                $this->temp['heading_previous_cell']
            );
            $out .= "\n";
        }
        
        // Heading containing the month/year
        $colspan = $this->showNextPrev ? 5 : 7;
        
        $this->temp['heading_title_cell'] = str_replace(
            '{colspan}', 
            $colspan, 
            $this->temp['heading_title_cell']
        );
        
        $this->temp['heading_title_cell'] = str_replace(
            '{heading}', 
            $this->getMonthName($month)."&nbsp;".$year, 
            $this->temp['heading_title_cell']
        );
        
        $out .= $this->temp['heading_title_cell'];
        $out .= "\n";
        
        // "next" month link
        if ($this->showNextPrev) {
            $adjustedDate = $this->adjustDate($month + 1, $year);
            $out .= str_replace(
                '{next_url}', 
                $this->nextPrevUrl.$adjustedDate['year'].'/'.$adjustedDate['month'], 
                $this->temp['heading_next_cell']
            );
        }
        
        $out .= "\n";        
        $out .= $this->temp['heading_row_end'];
        $out .= "\n";
        
        // Write the cells containing the days of the week
        $out .= "\n";    
        $out .= $this->temp['week_row_start'];
        $out .= "\n";
        
        $dayNames = $this->getDayNames();
        
        for ($i = 0; $i < 7; $i++) {
            $out .= str_replace(
                '{week_day}', 
                $dayNames[($startDay + $i) % 7], 
                $this->temp['week_day_cell']
            );
        }
        
        $out .= "\n";
        $out .= $this->temp['week_row_end'];
        $out .= "\n";
        
        // Build the main body of the calendar
        while ($day <= $totalDays) {
            $out .= "\n";
            $out .= $this->temp['cal_row_start'];
            $out .= "\n";
            
            for ($i = 0; $i < 7; $i++) {
                $out .= ($isCurrentMonth && $day === $curDay) 
                    ? $this->temp['cal_cell_start_today'] 
                    : $this->temp['cal_cell_start'];
                
                $out = str_replace('{week_day}', $dayNames[($startDay + $i) % 7], $out);
                
                if ($day > 0 && $day <= $totalDays) {
                    if (isset($data[$day])) {
                        // Cells with content
                        $temp = ($isCurrentMonth && $day === $curDay) 
                            ? $this->temp['cal_cell_content_today'] 
                            : $this->temp['cal_cell_content'];
                        
                        $out .= str_replace(
                            ['{day}', '{content}'],
                            [$day, $data[$day]],
                            $temp
                        );
                    } else {
                        // Cells with no content
                        $temp = ($isCurrentMonth && $day === $curDay) 
                            ? $this->temp['cal_cell_no_content_today'] 
                            : $this->temp['cal_cell_no_content'];
                        
                        $out .= str_replace('{day}', $day, $temp);
                    }
                } else {
                    // Blank cells
                    $out .= $this->temp['cal_cell_blank'];
                }
                
                $out .= ($isCurrentMonth && $day === $curDay) 
                    ? $this->temp['cal_cell_end_today'] 
                    : $this->temp['cal_cell_end'];
                
                $day++;
            }
            
            $out .= "\n";        
            $out .= $this->temp['cal_row_end'];
            $out .= "\n";        
        }
        
        $out .= "\n";        
        $out .= $this->temp['table_close'];
        
        return $out;
    }
    
    /**
     * Get Month Name
     */
    public function getMonthName(int $month): string
    {
        if ($this->monthType === 'short') {
            $monthNames = [
                '01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr', 
                '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago', 
                '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic'
            ];
        } else {
            $monthNames = [
                '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', 
                '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio', 
                '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre', 
                '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
            ];
        }
        
        $month = str_pad($month, 2, '0', STR_PAD_LEFT);
        return $monthNames[$month] ?? '';
    }
    
    /**
     * Get Day Names
     */
    public function getDayNames(?string $dayType = null): array
    {
        $dayType = $dayType ?? $this->dayType;
        
        if ($dayType === 'long') {
            $dayNames = [
                'Domingo', 'Lunes', 'Martes', 'Miércoles', 
                'Jueves', 'Viernes', 'Sábado'
            ];
        } elseif ($dayType === 'short') {
            $dayNames = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        } else {
            $dayNames = ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá'];
        }
        
        return $dayNames;
    }
    
    /**
     * Adjust Date
     */
    public function adjustDate(int $month, int $year): array
    {
        $date = ['month' => $month, 'year' => $year];
        
        while ($date['month'] > 12) {
            $date['month'] -= 12;
            $date['year']++;
        }
        
        while ($date['month'] <= 0) {
            $date['month'] += 12;
            $date['year']--;
        }
        
        if (strlen($date['month']) === 1) {
            $date['month'] = '0'.$date['month'];
        }
        
        return $date;
    }
    
    /**
     * Total days in a given month
     */
    public function getTotalDays(int $month, int $year): int
    {
        $daysInMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        
        if ($month < 1 || $month > 12) {
            return 0;
        }
        
        // Is the year a leap year?
        if ($month === 2) {
            if ($year % 400 === 0 || ($year % 4 === 0 && $year % 100 !== 0)) {
                return 29;
            }
        }
        
        return $daysInMonth[$month - 1];
    }
    
    /**
     * Set Default Template Data
     */
    protected function defaultTemplate(): array
    {
        return [
            'table_open' => '<table border="0" cellpadding="4" cellspacing="0">',
            'heading_row_start' => '<tr>',
            'heading_previous_cell' => '<th><a href="{previous_url}">&lt;&lt;</a></th>',
            'heading_title_cell' => '<th colspan="{colspan}">{heading}</th>',
            'heading_next_cell' => '<th><a href="{next_url}">&gt;&gt;</a></th>',
            'heading_row_end' => '</tr>',
            'week_row_start' => '<tr>',
            'week_day_cell' => '<td>{week_day}</td>',
            'week_row_end' => '</tr>',
            'cal_row_start' => '<tr>',
            'cal_cell_start' => '<td>',
            'cal_cell_start_today' => '<td>',
            'cal_cell_content' => '<a href="{content}">{day}</a>',
            'cal_cell_content_today' => '<a href="{content}"><strong>{day}</strong></a>',
            'cal_cell_no_content' => '{day}',
            'cal_cell_no_content_today' => '<strong>{day}</strong>',
            'cal_cell_blank' => '&nbsp;',
            'cal_cell_end' => '</td>',
            'cal_cell_end_today' => '</td>',
            'cal_row_end' => '</tr>',
            'table_close' => '</table>'
        ];    
    }
    
    /**
     * Parse Template
     */
    protected function parseTemplate(): void
    {
        $this->temp = $this->defaultTemplate();
        
        if (empty($this->template)) {
            return;
        }
        
        $today = [
            'cal_cell_start_today', 
            'cal_cell_content_today', 
            'cal_cell_no_content_today', 
            'cal_cell_end_today'
        ];
        
        foreach ([
            'table_open', 'table_close', 'heading_row_start', 'heading_previous_cell', 
            'heading_title_cell', 'heading_next_cell', 'heading_row_end', 'week_row_start', 
            'week_day_cell', 'week_row_end', 'cal_row_start', 'cal_cell_start', 
            'cal_cell_content', 'cal_cell_no_content', 'cal_cell_blank', 'cal_cell_end', 
            'cal_row_end', 'cal_cell_start_today', 'cal_cell_content_today', 
            'cal_cell_no_content_today', 'cal_cell_end_today'
        ] as $val) {
            if (preg_match("/\{".$val."\}(.*?)\{\/".$val."\}/si", $this->template, $match)) {
                $this->temp[$val] = $match[1];
            } elseif (in_array($val, $today, true)) {
                $this->temp[$val] = $this->temp[str_replace('_today', '', $val)];
            }
        }    
    }
}
