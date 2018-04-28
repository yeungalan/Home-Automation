#include <SoftwareSerial.h>

SoftwareSerial ESP(3, 2); // ESP8266 ESP-01: Tx, Rx
 
void setup() {
    Serial.begin(115200);
    ESP.begin(115200);
   
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
    char cipSend[256];
    char cipClose[256];
    sprintf(html,"<html><head><title><body></body></html>",connID,msg);
    sprintf(cipSend,"AT+CIPSEND=%d,%d\r\n",connID,strlen(html));
    sprintf(cipClose,"AT+CIPCLOSE=%d\r\n",connID);
    sendATcmd(cipSend,1000);
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
            sendHTML(connID,msg.c_str()); // send HTML message to client
            delay(100);
        }
    }
}

