#![deny(warnings)]

use hyper::service::{make_service_fn, service_fn};
use hyper::{Server, Request, Body, Response};
use std::net::SocketAddr;
use std::process::{Command, Stdio};
use std::convert::Infallible;
//use std::io::Write;

async fn hello_world(_req: Request<Body>) -> Result<Response<Body>, Infallible> {
    Ok(Response::new("Hello, World".into()))
}

#[tokio::main]
async fn main() {
    let child = Command::new("/usr/bin/php")
        .arg("/data/Development/phpinnacle/amridge/examples/http/worker.php")
        .stdin(Stdio::piped())
        .stdout(Stdio::piped())
        .spawn().unwrap();

    println!("Spawn worker");

//    let child_stdin = child.stdin.as_mut().unwrap();
//    child_stdin.write_all(b"Hello, world!\n").unwrap();

    let output = child.wait_with_output().unwrap();

    println!("output = {:?}", output);

    let make_service = make_service_fn(|_| {
        async {

            // service_fn converts our function into a `Service`
            Ok::<_, Infallible>(service_fn(hello_world))
        }
    });

    let addr = SocketAddr::from(([127, 0, 0, 1], 3000));
    let server = Server::bind(&addr).serve(make_service);

    println!("Listening on http://{}", addr);

    if let Err(e) = server.await {
        eprintln!("Server error: {}", e);
    }
}
