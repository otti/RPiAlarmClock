// gcc -D_REENTRANT AlarmClock.c -lpthread -I/usr/include/libxml2 -lxml2 -o AlarmClock

#include <string.h>
#include <stdio.h>
#include <pthread.h>
#include <time.h>
#include <ctype.h>
#include <sys/stat.h>
#include <libxml/xmlmemory.h>
#include <libxml/parser.h>

#define XML_FILE "/var/www/wecker/actions.xml"
#define MAX_PWM_VALUE 1023
/*
const int log_pwm_value[] ={
							 10,  11,  12,  13,  15,  16,  18,  19,  21,  23,
							 26,  28,  31,  34,  38,  41,  45,  50,  55,  60,
							 66,  73,  80,  88,  96, 106, 117, 128, 141, 155,
							170, 187, 205, 226, 248, 273, 300, 329, 362, 398,
							437, 481, 528, 580, 638, 701, 771, 847, 931, 1024
						 };*/
						
const int log_pwm_value[]={
						  10,  10,  11,  12,  12,  13,  13,  14,  15,   15,
						  16,  17,  18,  18,  19,  20,  21,  22,  23,   24,
						  25,  27,  28,  29,  31,  32,  34,  35,  37,   39,
						  41,  43,  45,  47,  49,  51,  54,  56,  59,   62,
						  65,  68,  71,  75,  78,  82,  86,  90,  94,   99,
						 104 ,108, 114, 119, 125, 131, 137, 144, 150,  158,
						 165, 173, 181, 190, 199, 209, 219, 229, 240,  252,
						 264, 276, 290, 303, 318, 333, 349, 366, 383,  402,
						 421, 441, 462, 484, 507, 532, 557, 584, 612,  641,
						 672, 704, 738, 773, 810, 849, 889, 932, 976, 1023
};

typedef struct T_ACTION
{
	char InProgress;
	char Name[256];
	char Type[20];
	char PWM_Frequency[10];
	char PWM_Inverted[10];
	char Active[10];
	char Force[10];
	char Hour[10];
	char Minute[10];
	char RampTime[10];
	char LagTime[10];
	char MaxValue[10];
	char Stream[1024];
	char DaysToWork[7][20];
}TACTION;


// Global Variables
struct tm LastXMLModificationDate;
int ReloadXML;
int NoOfActions;
int InvertedPWM = 0;
int PWMFrequency = 200; // Default 200 Hz

TACTION TimedActions[10];


int GetMinute(void)
{
	time_t t;
	struct tm *ts;
	
	t = time(NULL);
    ts = localtime(&t);
	
    return ts->tm_min;
}

int GetHour(void)
{
	time_t t;
	struct tm *ts;
	
	t = time(NULL);
    ts = localtime(&t);
	
    return ts->tm_hour;
    return ts->tm_hour;
}

int CheckDayOfWeek(char* DayOfWeek)
{
	time_t t;
	struct tm *ts;
	char* CmpDayOfWeek[] = { "Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday" };
	
	t = time(NULL);
    ts = localtime(&t);

	if( strcmp(DayOfWeek, CmpDayOfWeek[ts->tm_wday]) )
		return 0;
	else
		return 1;

}

void Audio_SetVolume(int value)
{
	char cmd[50];
	sprintf(cmd, "mpc -q --wait volume %i", value);
	printf("Volume: %i\%\r\n", value);
	system(cmd);
}

void Audio_Stop(void)
{
	system("mpc -q --wait stop");
}

void Audio_Start(char *Stream)
{
	char cmd[512];
	
	system("mpc -q --wait clear");
	sprintf(cmd, "mpc -q --wait add %s", Stream);
	system(cmd);
	system("mpc -q --wait play");
}



void SetPWMValue(int value)
{
	char cmd[128];
	//char debug[255];
	
	printf("PWM Value: %i\%\r\n", value);
	
	if( value > 100 )
	{
		printf("Error: Invalid PWM Value (%i)\r\n", value);
		return;
	}
	
	if( InvertedPWM )
	{
		if( value == 0 )
			value = MAX_PWM_VALUE;
		else
			value = log_pwm_value[100-value];	
	}
	else
	{	
		if( value == 0 )
			value = 0;
		else
			value = log_pwm_value[value-1];
	}

	sprintf(cmd, "/usr/local/bin/gpio pwm 1 %i", value);
	system(cmd);
	
	//sprintf(debug, "echo \"%s\" >> /root/AlarmClock/debug.txt", cmd);
	//system(debug);
	
	
}

void Init_PWM(void)
{
	int freq;
	char cmd[128];
	
	//char debug[255];
	
	system("/usr/local/bin/gpio mode 1 pwm");
	system("/usr/local/bin/gpio pwm-ms");
	system("/usr/local/bin/gpio pwmr 1024");
	
	if( PWMFrequency == 0 ) // div / 0
		PWMFrequency = 1;

	freq = (int)((1.0 / (float)PWMFrequency) * 18600.0);
	
	sprintf(cmd, "gpio pwmc %i", freq);
	system(cmd);
	
	//sprintf(debug, "echo \"%s\" >> /root/AlarmClock/debug.txt", cmd);
	//system(debug);
}

void GetXMLModificationDate(struct tm *ts)
{

	struct tm *tmp;
	struct stat s;
	stat(XML_FILE, &s);

	tmp = localtime(&s.st_mtime);
	*ts = *tmp;
}

void *DimmUp(void *foo)
{

	int RampTime;
	int LagTime;
	int MaxValue;
	int sec = 0;
	int SecPerStep;
	int RampIsActive = 1;
	int PwmValue;


	TACTION *TimedAction = foo;
	
	MaxValue = atoi(TimedAction->MaxValue);
	RampTime = atoi(TimedAction->RampTime) * 60;
	LagTime = atoi(TimedAction->LagTime) * 60;
	
	if( RampTime == 0 )
	{
		RampIsActive = 0;
		SetPWMValue(MaxValue);
	}
	

	SecPerStep = RampTime/MaxValue;
	if( SecPerStep == 0 ) // Div / 0
		SecPerStep = 1;
	
	while(1)
	{
		sec++;
		sleep(1);
		
		if( RampIsActive )
		{
			
			PwmValue = sec/SecPerStep;

			SetPWMValue(PwmValue);
			
				
			if( PwmValue >= MaxValue )
			{
				RampIsActive = 0;
				printf("PWM Ramp deactivated\r\n");
			}
		}


		if( sec >= (RampTime + LagTime) )
		{
			SetPWMValue(0);	
			
			TimedAction->InProgress = 0;
			printf("PWM Thread \"%s\" terminated\r\n", TimedAction->Name);
			return;
		}

	}
}

void *Music(void *foo)
{
	char cmd[256];
	int Volume = 0;
	int RampTime;
	int LagTime;
	int MaxValue;
	int sec = 0;
	int SecPerStep;
	int RampIsActive = 1;
	int PWM_Index = 0;

	TACTION *TimedAction = foo;

	MaxValue = atoi(TimedAction->MaxValue);
	RampTime = atoi(TimedAction->RampTime) * 60;
	LagTime = atoi(TimedAction->LagTime) * 60;

	SecPerStep = RampTime/MaxValue;

	if( SecPerStep == 0 ) // div / 0
		SecPerStep = 1;
		
	if( RampTime == 0 )
	{
		RampIsActive = 0;
		Audio_SetVolume(MaxValue);
	}

	system("mpc -q --wait volume 0");
	system("mpc -q --wait clear");
	sprintf(cmd, "mpc -q --wait add %s", TimedAction->Stream);
	system(cmd);
	system("mpc -q play");
	printf("play -----------------------\r\n");
	
	while(1)
	{
		sec++;
		sleep(1);
		
		if( RampIsActive )
		{
			Volume = sec/SecPerStep;
			Audio_SetVolume(Volume);
			if( Volume >= MaxValue)
			{
				RampIsActive = 0;
				printf("Ramp deactivated\r\n");
			}
		}
		
		if( sec >= (RampTime + LagTime) )
		{
			Audio_SetVolume(0);
			Audio_Stop();
			
			TimedAction->InProgress = 0;
			printf("Audio Thread \"%s\" terminated\r\n", TimedAction->Name);
			return;
		}

	}
}

void *CheckXMLModification(void *foo)
{

	struct tm NewXMLModificationDate;
	
	while(1)
	{
		sleep(1);

		GetXMLModificationDate(&NewXMLModificationDate);
		if( memcmp(&NewXMLModificationDate, &LastXMLModificationDate, sizeof(struct tm)) )
		{
			LastXMLModificationDate = NewXMLModificationDate;
			printf("XML File changed\r\n");
			ReloadXML = 1;
		}
		
	}
}

void die(char *msg)
{
  printf("%s", msg);
  return;
}

void CopyKeyToAction(char *ChildName, void *Data, xmlDocPtr doc, xmlNodePtr child)
{
	xmlChar *key;
	
	if ((!xmlStrcmp(child->name, ChildName)))
	{
		key = xmlNodeListGetString(doc, child->xmlChildrenNode, 1);
		strcpy(Data, key);
		xmlFree(key);
	}

}

int ReadXML(void)
{

	xmlChar *key;
	xmlDocPtr doc;
    xmlNodePtr cur;
	xmlNodePtr child;
	xmlNodePtr SubChild;
	
	xmlChar *uri;

	int ActionNr;
	int DayNr;
	 
    doc = xmlParseFile(XML_FILE);
 
    if (doc == NULL )
        die("Document parsing failed. \n");
 
    cur = xmlDocGetRootElement(doc); //Gets the root element of the XML Doc
 
    if (cur == NULL)
    {
        xmlFreeDoc(doc);
        die("Document is Empty!!!\n");
    }
 
    cur = cur->xmlChildrenNode;
	ActionNr = 0;
    while (cur != NULL)
    {
        if ((!xmlStrcmp(cur->name, "TimedAction")))
        {
		   uri = xmlGetProp(cur, "type");
		   strcpy(TimedActions[ActionNr].Type, uri);
		   xmlFree(uri);
		   
           child = cur;
		   child = child->xmlChildrenNode;
		    while (child != NULL)
			{
				CopyKeyToAction("Name", TimedActions[ActionNr].Name, doc, child);
				CopyKeyToAction("active", TimedActions[ActionNr].Active, doc, child);
				CopyKeyToAction("PWM_Frequency", TimedActions[ActionNr].PWM_Frequency, doc, child);
				CopyKeyToAction("PWM_Inverted", TimedActions[ActionNr].PWM_Inverted, doc, child);
				CopyKeyToAction("force", TimedActions[ActionNr].Force, doc, child);
				CopyKeyToAction("RampTime", TimedActions[ActionNr].RampTime, doc, child);
				CopyKeyToAction("LagTime", TimedActions[ActionNr].LagTime, doc, child);
				CopyKeyToAction("Value_max", TimedActions[ActionNr].MaxValue, doc, child);
				CopyKeyToAction("Stream", TimedActions[ActionNr].Stream, doc, child);
				
				
				if ((!xmlStrcmp(child->name, "StartTime")))
				{
					SubChild = child;
					SubChild = SubChild->xmlChildrenNode;
					while (SubChild != NULL)
					{
						if ((!xmlStrcmp(SubChild->name, "hour")))
						{
							key = xmlNodeListGetString(doc, SubChild->xmlChildrenNode, 1);
							strcpy(TimedActions[ActionNr].Hour, key);
							xmlFree(key);
						}
						if ((!xmlStrcmp(SubChild->name, "minute")))
						{
							key = xmlNodeListGetString(doc, SubChild->xmlChildrenNode, 1);
							strcpy(TimedActions[ActionNr].Minute, key);
							xmlFree(key);
						}
						SubChild = SubChild->next;
					}
				}
				
				if ((!xmlStrcmp(child->name, "daystowork")))
				{
					DayNr = 0;
					SubChild = child;
					SubChild = SubChild->xmlChildrenNode;
					while (SubChild != NULL)
					{
						if ((!xmlStrcmp(SubChild->name, "DayOfWeek")))
						{
							key = xmlNodeListGetString(doc, SubChild->xmlChildrenNode, 1);
							strcpy(TimedActions[ActionNr].DaysToWork[DayNr++], key);
							xmlFree(key);
						}
						SubChild = SubChild->next;
					}
				}
				
				child = child->next;
			}
			ActionNr++;
        }
        cur = cur->next;

    }
 
    xmlFreeDoc(doc);
	

    return ActionNr;
}

void PrintTimedAction(TACTION *Action)
{
	int i;
	
	printf("Name         : %s\r\n", Action->Name);
	printf("Type         : %s\r\n", Action->Type);
	printf("Active       : %s\r\n", Action->Active);
	printf("Hour         : %s\r\n", Action->Hour);
	printf("Minute       : %s\r\n", Action->Minute);
	printf("Force        : %s\r\n", Action->Force);
	printf("Ramp Time    : %s\r\n", Action->RampTime);
	printf("LagTime      : %s\r\n", Action->LagTime);
	printf("Max Value    : %s\r\n", Action->MaxValue);
	printf("PWM Frequency: %s\r\n", Action->PWM_Frequency);
	printf("PWM Inverted : %s\r\n", Action->PWM_Inverted);
	printf("Stream       : %s\r\n", Action->Stream);
	printf("DaysToWork   : ");
	for(i=0; i<7; i++)
		printf("%3.3s, ", Action->DaysToWork[i]);
	printf("\r\n");
	printf("---------------------------------\r\n\r\n");
}



int CheckEnable(void)
{
	int ActionId;
	int x;
	
	for(ActionId=0; ActionId<NoOfActions; ActionId++)
	{
		if( TimedActions[ActionId].InProgress == 0 )
		{
			if( !strcmp(TimedActions[ActionId].Active, "true") )
			{
				if( atoi(TimedActions[ActionId].Hour) == GetHour() )
				{
					if( atoi(TimedActions[ActionId].Minute) == GetMinute() )
					{
						for(x=0; x<7; x++)
						{
							if( CheckDayOfWeek(TimedActions[ActionId].DaysToWork[x]) )
							{
								TimedActions[ActionId].InProgress = 1;
								printf("------------------------------------------\r\n");
								printf("Action \"%s\" [%i] started\r\n", TimedActions[ActionId].Name, ActionId);
								printf("------------------------------------------\r\n");
								return ActionId;
							}
						}

					}
				}
			}
		}
	}
	
	return -1;
}

void CheckForcedAction(void)
{
	int ActionId;
	
	for(ActionId=0; ActionId<NoOfActions; ActionId++)
	{
		if( TimedActions[ActionId].InProgress == 0 )
		{
			if( (strcmp(TimedActions[ActionId].Name, "STATIC_LIGHT") == 0) || (strcmp(TimedActions[ActionId].Name, "STATIC_RADIO")==0) )
			{
				if( !strcmp(TimedActions[ActionId].Force, "true") )
				{
					TimedActions[ActionId].InProgress = 1;
					printf("------------------------------------------\r\n");
					printf("Action \"%s\" [%i] started\r\n", TimedActions[ActionId].Name, ActionId);
					printf("------------------------------------------\r\n");
					
					if( !strcmp(TimedActions[ActionId].Type, "TimedAction_PWM") )
					{
						SetPWMValue(atoi(TimedActions[ActionId].MaxValue));
					}
					
					if( !strcmp(TimedActions[ActionId].Type, "TimedAction_Music") )
					{
						Audio_Start(TimedActions[ActionId].Stream);
						Audio_SetVolume(atoi(TimedActions[ActionId].MaxValue));
					}
				}
			}
		}
	}
	
	for(ActionId=0; ActionId<NoOfActions; ActionId++)
	{
		if( TimedActions[ActionId].InProgress == 1 )
		{
			if( (strcmp(TimedActions[ActionId].Name, "STATIC_LIGHT") == 0) || (strcmp(TimedActions[ActionId].Name, "STATIC_RADIO")==0) )
			{
				if( !strcmp(TimedActions[ActionId].Force, "false") )
				{
					TimedActions[ActionId].InProgress = 0;
					printf("------------------------------------------\r\n");
					printf("Action \"%s\" [%i] stoped\r\n", TimedActions[ActionId].Name, ActionId);
					printf("------------------------------------------\r\n");
					
					if( !strcmp(TimedActions[ActionId].Type, "TimedAction_PWM") )
					{
						SetPWMValue(0);
					}
					
					if( !strcmp(TimedActions[ActionId].Type, "TimedAction_Music") )
					{
						Audio_Stop();
						Audio_SetVolume(0);
					}
				}
			}
		}
	}
}


int main ()
{
	char bar = '-';
	ReloadXML = 1;
	int ActionId;
	
	pthread_t LightThread;
	pthread_t MusicThread;
	pthread_t CheckXMLModificationThread;
	
	GetXMLModificationDate(&LastXMLModificationDate);
	
	pthread_create (&CheckXMLModificationThread, NULL, CheckXMLModification, &bar);
	
	
	while(1)
	{
		sleep(1);
		
		if( ReloadXML )
		{
			ReloadXML = 0;
			
			pthread_cancel (MusicThread);
			pthread_join (MusicThread, NULL);
			
			pthread_cancel (LightThread);
			pthread_join (LightThread, NULL);
			
			memset(TimedActions, 0, sizeof(TimedActions));
			NoOfActions = ReadXML();

			InvertedPWM = 0; 
			PWMFrequency = 200; // Default 200 Hz
			for(ActionId=0; ActionId<NoOfActions; ActionId++)
			{
				if( !strcmp(TimedActions[ActionId].PWM_Inverted, "true") )
					InvertedPWM = 1;
				if( atoi(TimedActions[ActionId].PWM_Frequency) != 0 )
					PWMFrequency = atoi(TimedActions[ActionId].PWM_Frequency);
			}
			
			Init_PWM();
			SetPWMValue(0);	
			Audio_SetVolume(0);
			Audio_Stop();

			/*
			PrintTimedAction(&TimedActions[0]);
			PrintTimedAction(&TimedActions[1]);
			PrintTimedAction(&TimedActions[2]);
			PrintTimedAction(&TimedActions[3]);*/
		}
		
		CheckForcedAction();
		
		ActionId = CheckEnable();
		if( ActionId >= 0 )
		{
			if( !strcmp(TimedActions[ActionId].Type, "TimedAction_PWM") )
				pthread_create (&LightThread, NULL, DimmUp, &TimedActions[ActionId]);
	
			if( !strcmp(TimedActions[ActionId].Type, "TimedAction_Music") )
				pthread_create (&MusicThread, NULL, Music, &TimedActions[ActionId]);
		}
		
	}

	
	return 0;
}