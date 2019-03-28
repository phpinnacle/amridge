package main

import (
	"fmt"
	"github.com/spiral/goridge"
	"log"
	"net"
	"net/rpc"
)

// App sample
type App struct{}

// Hi returns greeting message.
func (a *App) Hi(name string, r *string) error {
	*r = fmt.Sprintf("Hello, %s!", name)
	return nil
}

func main() {
	ln, err := net.Listen("unix", "/tmp/server.sock")
	if err != nil {
		panic(err)
	}

	rpc.Register(new(App))
	log.Printf("started")

	for {
		conn, err := ln.Accept()
		if err != nil {
			continue
		}

		log.Printf("new connection %+v", conn)
		go rpc.ServeCodec(goridge.NewCodec(conn))
	}
}
