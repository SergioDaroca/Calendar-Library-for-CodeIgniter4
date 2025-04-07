<?PHP

/***************************************************************************************************
 * ORIGINAL DOCUMENTATION (Codeigniter 1.7.2)
 http://www2.cs.uidaho.edu/~jeffery/courses/384/gus/php/http/user_guide/libraries/calendar.html
 *
 * Code snippets below tested in Codeigniter 4.6
 *
 ***************************************************************************************************/


/*
 * Code in my main controller
 *
 ***************************************************************************************************/
    private function getCalendar($uri)
    {
        //Instantiate Calendar Model (it handles getting the calendar events data from the database, and rendering the calendar months using the calendar library)
        $Calendar = new Calendar();
        
        //Dates from config files
        $concertYear = config('App')->calendar_concertYear;
        $firstMonth = config('App')->firstMonth;
        $lastMonth = config('App')->lastMonth;

        //The calendar model initializes the Calendar library
        $Calendar->initCalendar();
        

        //Get for each month the events data and then generate that month's calendar
        for($monthNumber = $firstMonth; $monthNumber <= $lastMonth; $monthNumber++) {
            $content .= '<h2 class="calendarName">'.$Calendar->monthNames[$monthNumber].'</h2>';
            //Get the events data for the month (they are stored in the model)
            $Calendar->getEvents($concertYear,$monthNumber);
            //Generate the calendar month
            $content .= $Calendar->getCalendar($concertYear, $monthNumber);
        }
        
        return $content;
    }


/*
 * Code in my Calendar Model
 *
 ***************************************************************************************************/

namespace App\Models;

use CodeIgniter\Model;
use Config\Services;
use App\Libraries\CI_4_Calendar;

class Calendar extends Model
{
    //The CI_4_Calendar instance
    protected $calendarLib;
    //The calendar events data that will be passed to the Calendar Library
    protected $calendarData = [];
  
    public function __construct()
    {
        parent::__construct();
        $this->db = \Config\Database::connect();
      
        /**[...among other things]**/
      
        //Load the CI_4_Calendar Library
        $this->calendarLib = new CI_4_Calendar();
    }

    /*
    * Initialize the Calendar Library with its preferences and template
    */
    public function initCalendar(): void
    {
        $prefs = [
            'start_day' => 'monday',
            'month_type' => 'long',
            'day_type' => 'long',
            'show_next_prev' => false,
            'next_prev_url' => site_url('nochesalcazar'.$this->config->calendar_concertYear.'/calendario/')
        ];
        
        $prefs['template'] = '
            {table_open}<table class="EventCalendar">{/table_open}
            {heading_row_start}{/heading_row_start}
            {heading_previous_cell}{/heading_previous_cell}
            {heading_title_cell}{/heading_title_cell}
            {heading_next_cell}{/heading_next_cell}
            {heading_row_end}{/heading_row_end}
            {week_row_start}<tr>{/week_row_start}
            {week_day_cell}<td class="week_day {week_day}">{week_day}</td>{/week_day_cell}
            {week_row_end}</tr>{/week_row_end}
            {cal_row_start}<tr>{/cal_row_start}
            {cal_cell_start}<td class="{week_day}">{/cal_cell_start}
            {cal_cell_content}<div class="date_has_event"><div class="day">{day}</div>{content}</div>{/cal_cell_content}
            {cal_cell_content_today}<div class="date_has_event"><div class="day today">{day}</div>{content}</div>{/cal_cell_content_today}
            {cal_cell_no_content}<div class="date_wo_event"><div class="NoEventText"> [No hay concierto]</div><div class="day">{day}</div></div>{/cal_cell_no_content}
            {cal_cell_no_content_today}<div class="today date_wo_event"><div class="day">{day}</div></div>{/cal_cell_no_content_today}
            {cal_cell_blank}&nbsp;{/cal_cell_blank}
            {cal_cell_end}</td>{/cal_cell_end}
            {cal_row_end}</tr>{/cal_row_end}
            {table_close}</table>{/table_close}
        ';
        
        $this->calendarLib->initialize($prefs);
    }

    /*
    * Get events for a specific month/year
    */
    public function getEvents(int $year, int $month): string
    {
        $this->calendarData = [];
        $res = '<calendar class="calendario"><ul>';
        //Get and parse the events from the database
        $this->allNews = $this->getAllEventsForCalendar($year, $month);
        
        if (empty($this->allNews)) {
            return $res . '</ul></calendar>';
        }
        
        $currentMonth = 0;
        
        foreach ($this->allNews as $item) {
            if ($item['enabled'] == 1) {
                $lastItemMonth = $currentMonth;
                $date = explode('/', $item['event_date']);
                //Day of the month to be used as index in the event data passed to the Calendar library in $this->calendarData["$currentDay"]
                $currentDay = $date[0];
                $currentMonth = (int)$date[1];
                
                if ($currentMonth == $month) {
                    $eventContent = $this->dbGetContentById($item['id']);
                    preg_match_all('/<img[^>]+>/i', $eventContent, $imgs);
                    $eventContent = $imgs[0][0] ?? '';
                    
                    $item['label'] = $this->config->musicas[$item['label']];
                    $item['title'] = str_replace('+', '+<br/>', $item['title']);
                    $link = str_replace('/concerts_'.$this->config->calendar_concertYear.'/', '', $item['url']);
                    
                    $li = anchor($link, $item['title'], 'class="'.$item['label'].'" title="'.$item['title'].'"');
                    $eventContent = anchor($link, $eventContent, 'class="'.$item['label'].'" title="'.$item['title'].'"');
                    
                    $res .= '<li class="concert event li_event">';
                    $res .= '<concert>'.$eventContent.'<p class="grupo">'.$li.'</p><date>'.$currentDay.'</date></concert>';
                    $res .= '</li>';
                    //This variable 'calendarData' is the one that will be used to render the calendar
                    $this->calendarData["$currentDay"] = '<concert class="event '.$item['label'].'">'.$li.'<div class="day">'.$currentDay.'</div></concert>';
                }
            }
        }
        
        $res .= '</ul></calendar>';
        return $res;
    }

    /*
    * Generate calendar HTML
    */
    public function getCalendar(int $year, int $month): string
    {
        return $this->calendarLib->generate($year, $month, $this->calendarData);
    }

?>
