

use crate::config::Config;
use crate::server::Server;
use clap::{App, Arg};

mod process;
mod server;
mod config;
//
//async fn proxy(client: Arc<Handler>, req: Request<Body>) -> Result<Response<Body>, Error> {
//    client.handle(req).await
//}

async fn signal() {
    tokio::signal::ctrl_c()
        .await
        .expect("Failed to install interrupt signal handler");
}

#[tokio::main]
async fn main() {
    let matches = App::new("amridge")
        .about("PHP Application Server")
        .version("1.0")
        .author("PHPinnacle Developers")
        .subcommand(
            App::new("serve")
                .about("Start serving HTTP with provided configuration")
                .arg(
                    Arg::with_name("config")
                        .help("Config file to use")
                        .default_value("config.toml")
                ),
        )
        .get_matches();

    match matches.subcommand() {
        ("serve", Some(serve)) => {
            let config = Config::new(serve.value_of("config").unwrap().into()).unwrap();
            let server = Server::new(config);

            server.serve().await;
        }
        ("", None) => println!("No subcommand was used"),
        _ => unreachable!(),
    }
//
//    let cmd = "/usr/bin/php".to_string();
//    let args = vec!["/data/Development/phpinnacle/amridge/examples/http/worker.php".to_string()];
//
//    let pool = ProcessPool::create(4, cmd, args).await.unwrap();
//    let arc = Arc::new(Handler::create(pool));
//
//    let service = make_service_fn(move |_conn| {
//        let handler = arc.clone();
//
//        async move { Ok::<_, Error>(service_fn(move |req| proxy(handler.clone(), req))) }
//    });
//
//    let addr = ([127, 0, 0, 1], 3000).into();
//    let server = Server::bind(&addr).serve(service);
//
//    // And now add a graceful shutdown signal...
//    let graceful = server.with_graceful_shutdown(signal());
//
//    // Run this server for... forever!
//    if let Err(e) = graceful.await {
//        eprintln!("Server Error: {}", e);
//    }
}
