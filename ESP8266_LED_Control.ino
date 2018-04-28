#include <SoftwareSerial.h>

SoftwareSerial ESP(3, 2); // ESP8266 ESP-01: Tx, Rx
 int ledPin = 12;
 bool setOn = false;
 bool query = false;
void setup() {
    Serial.begin(115200);
    ESP.begin(115200);

     pinMode(ledPin, OUTPUT);
   
    Serial.println("\nInitialize ESP-01 ...");
    sendATcmd("AT+RST\r\n",3000);       // reset ESP-01
    sendATcmd("AT+CIPMUX=1\r\n",2000);  // allow multiple access
    sendATcmd("AT+CIPSERVER=1,80\r\n",2000); // start server at port:80
    Serial.println("\nServer started at port 80 ...");
}

void setDelay(unsigned int delay) {
    unsigned long timeout = delay+millis();
    while( millis()<timeout ) {} // NOP
}

void sendATcmd(char* cmd, unsigned int delay) {
    ESP.print( cmd ); // send AT command to ESP-01
    setDelay(delay);
}

void sendHTML(byte connID,char* msg) {
    char html[256];
    char header[256];
    char cipSendhtml[256];
    char cipSendheader[256];
    char cipClose[256];
    sprintf(header,"HTTP/1.1 200 OK\r\nServer: HAClient-yeungalan\r\nContent-Type: text/plain\r\n\r\n",connID,msg);

    if(setOn == false){
        sprintf(html,"{\"value\":\"0\"}",connID,msg);
    digitalWrite(ledPin, LOW);
    }else if(setOn == true){
           sprintf(html,"{\"value\":\"1\"}",connID,msg);
    digitalWrite(ledPin, HIGH);
    }else if(query == true){
      sprintf(html,"0",connID,msg);
      query = false;
    }
  
    
    sprintf(cipSendhtml,"AT+CIPSEND=%d,%d\r\n",connID,strlen(html));
    sprintf(cipSendheader,"AT+CIPSEND=%d,%d\r\n",connID,strlen(header));
    sprintf(cipClose,"AT+CIPCLOSE=%d\r\n",connID);
    sendATcmd(cipSendheader,1000);
    sendATcmd(header,1000);
    sendATcmd(cipSendhtml,1000);
    sendATcmd(html,1000);
    sendATcmd(cipClose,1000);
}

void loop() {
    // send AT command to ESP-01 form console (serial)
    if ( Serial.available() ) {
        ESP.write( Serial.read() );
    }
    if ( ESP.available() ) { // receive message from ESP-01
        if ( ESP.find("+IPD,") ) { // detect the client's request
            String msg="";
            byte connID = ESP.read()-48; // client's connection ID
            while( ESP.available() ) { // client's request from the web browser
                msg += (char)ESP.read();
            }
            Serial.print(msg);
            if(msg.indexOf("/setOn/1") > 0){
                      setOn = true;
            }else if(msg.indexOf("/setOn/0") > 0){
                     setOn = false;
            }else if(msg.indexOf("/getOn") > 0){
                query = true;
            }
            sendHTML(connID,msg.c_str()); // send HTML message to client
            delay(100);
        }
    }
}

